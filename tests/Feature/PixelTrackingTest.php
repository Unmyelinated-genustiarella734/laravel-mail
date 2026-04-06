<?php

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Enums\TrackingEventType;
use JeffersonGoncalves\LaravelMail\Enums\TrackingProvider;
use JeffersonGoncalves\LaravelMail\Events\MailClicked;
use JeffersonGoncalves\LaravelMail\Events\MailOpened;
use JeffersonGoncalves\LaravelMail\Listeners\InjectTrackingPixel;
use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;
use JeffersonGoncalves\LaravelMail\Services\PixelTracker;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    config()->set('laravel-mail.tracking.pixel.open_tracking', true);
    config()->set('laravel-mail.tracking.pixel.click_tracking', true);
    config()->set('laravel-mail.tracking.pixel.route_prefix', 'mail/t');
    config()->set('laravel-mail.tracking.pixel.signing_key', 'test-signing-key');
});

// --- Pixel Route Tests ---

it('serves a 1x1 transparent gif on pixel route', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    $tracker = new PixelTracker;
    $url = $tracker->generatePixelUrl($mailLog->id);
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    $response = $this->get($path.'?'.$query);

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate');
});

it('records open event when pixel is loaded', function () {
    Event::fake([MailOpened::class]);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    $tracker = new PixelTracker;
    $url = $tracker->generatePixelUrl($mailLog->id);
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    $this->get($path.'?'.$query)->assertOk();

    $event = MailTrackingEvent::where('mail_log_id', $mailLog->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->type)->toBe(TrackingEventType::Opened)
        ->and($event->provider)->toBe(TrackingProvider::Pixel);

    Event::assertDispatched(MailOpened::class);
});

it('still serves pixel gif with invalid signature', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    $response = $this->get('/mail/t/pixel/'.$mailLog->id.'?sig=invalid');

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    expect(MailTrackingEvent::where('mail_log_id', $mailLog->id)->count())->toBe(0);
});

it('still serves pixel gif for non-existent mail log', function () {
    $tracker = new PixelTracker;
    $fakeId = 'non-existent-uuid';
    $url = $tracker->generatePixelUrl($fakeId);
    $path = parse_url($url, PHP_URL_PATH);
    $query = parse_url($url, PHP_URL_QUERY);

    $response = $this->get($path.'?'.$query);

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/gif');
});

// --- Click Route Tests ---

it('records click event and redirects to original url', function () {
    Event::fake([MailClicked::class]);

    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Delivered,
    ]);

    $originalUrl = 'https://example.com/page';
    $tracker = new PixelTracker;
    $clickUrl = $tracker->generateClickUrl($mailLog->id, $originalUrl);
    $path = parse_url($clickUrl, PHP_URL_PATH);
    $query = parse_url($clickUrl, PHP_URL_QUERY);

    $response = $this->get($path.'?'.$query);

    $response->assertRedirect($originalUrl);

    $event = MailTrackingEvent::where('mail_log_id', $mailLog->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->type)->toBe(TrackingEventType::Clicked)
        ->and($event->provider)->toBe(TrackingProvider::Pixel)
        ->and($event->url)->toBe($originalUrl);

    Event::assertDispatched(MailClicked::class);
});

it('returns 403 for click with invalid signature', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    $encodedUrl = base64_encode('https://example.com');

    $response = $this->get('/mail/t/click/'.$mailLog->id.'?url='.$encodedUrl.'&sig=invalid');

    $response->assertForbidden();
});

it('returns 400 for click with unsafe url', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    $unsafeUrl = 'javascript:alert(1)';
    $tracker = new PixelTracker;
    $clickUrl = $tracker->generateClickUrl($mailLog->id, $unsafeUrl);
    $path = parse_url($clickUrl, PHP_URL_PATH);
    $query = parse_url($clickUrl, PHP_URL_QUERY);

    $response = $this->get($path.'?'.$query);

    $response->assertStatus(400);
});

it('returns 400 for click with invalid base64 url', function () {
    $mailLog = MailLog::create([
        'subject' => 'Test',
        'from' => [['email' => 'from@example.com', 'name' => '']],
        'to' => [['email' => 'to@example.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    $response = $this->get('/mail/t/click/'.$mailLog->id.'?url=!!!invalid!!!&sig=something');

    $response->assertStatus(400);
});

// --- Listener Tests ---

it('injects tracking pixel into email html body', function () {
    $email = new Email;
    $email->html('<html><body><p>Hello</p></body></html>');
    $email->getHeaders()->addTextHeader('X-LaravelMail-ID', 'test-listener-uuid');

    $event = new MessageSending($email);

    $listener = new InjectTrackingPixel;
    $listener->handle($event);

    $html = $email->getHtmlBody();

    expect($html)->toContain('mail/t/pixel/test-listener-uuid')
        ->toContain('width="1" height="1"');
});

it('rewrites links in email html body when click tracking is enabled', function () {
    $email = new Email;
    $email->html('<html><body><a href="https://example.com">Link</a></body></html>');
    $email->getHeaders()->addTextHeader('X-LaravelMail-ID', 'test-listener-uuid');

    $event = new MessageSending($email);

    $listener = new InjectTrackingPixel;
    $listener->handle($event);

    $html = $email->getHtmlBody();

    expect($html)->toContain('mail/t/click/test-listener-uuid')
        ->not->toContain('href="https://example.com"');
});

it('does not inject pixel when open tracking is disabled', function () {
    config()->set('laravel-mail.tracking.pixel.open_tracking', false);

    $email = new Email;
    $email->html('<html><body><p>Hello</p></body></html>');
    $email->getHeaders()->addTextHeader('X-LaravelMail-ID', 'test-uuid');

    $event = new MessageSending($email);

    $listener = new InjectTrackingPixel;
    $listener->handle($event);

    $html = $email->getHtmlBody();

    expect($html)->not->toContain('mail/t/pixel/');
});

it('does not rewrite links when click tracking is disabled', function () {
    config()->set('laravel-mail.tracking.pixel.click_tracking', false);

    $email = new Email;
    $email->html('<html><body><a href="https://example.com">Link</a></body></html>');
    $email->getHeaders()->addTextHeader('X-LaravelMail-ID', 'test-uuid');

    $event = new MessageSending($email);

    $listener = new InjectTrackingPixel;
    $listener->handle($event);

    $html = $email->getHtmlBody();

    expect($html)->toContain('href="https://example.com"')
        ->not->toContain('mail/t/click/');
});

it('does nothing when email has no html body', function () {
    $email = new Email;
    $email->text('Plain text only');
    $email->getHeaders()->addTextHeader('X-LaravelMail-ID', 'test-uuid');

    $event = new MessageSending($email);

    $listener = new InjectTrackingPixel;
    $listener->handle($event);

    expect($email->getHtmlBody())->toBeNull();
});

it('does nothing when X-LaravelMail-ID header is missing', function () {
    $email = new Email;
    $email->html('<html><body><p>Hello</p></body></html>');

    $event = new MessageSending($email);

    $listener = new InjectTrackingPixel;
    $listener->handle($event);

    $html = $email->getHtmlBody();

    expect($html)->not->toContain('mail/t/pixel/')
        ->not->toContain('mail/t/click/');
});
