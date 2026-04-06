<?php

use Illuminate\Support\Carbon;
use JeffersonGoncalves\LaravelMail\Enums\MailStatus;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

it('prunes old mail logs', function () {
    $old = MailLog::create([
        'subject' => 'Old Email',
        'from' => [['email' => 'a@b.com', 'name' => '']],
        'to' => [['email' => 'c@d.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);
    // Force created_at to the past (not mass-assignable)
    MailLog::where('id', $old->id)->update(['created_at' => Carbon::now()->subDays(31)]);

    MailLog::create([
        'subject' => 'Recent Email',
        'from' => [['email' => 'a@b.com', 'name' => '']],
        'to' => [['email' => 'c@d.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    expect(MailLog::count())->toBe(2);

    $this->artisan('mail:prune')
        ->expectsOutputToContain('Pruned 1 mail log(s)')
        ->assertSuccessful();

    expect(MailLog::count())->toBe(1)
        ->and(MailLog::first()->subject)->toBe('Recent Email');
});

it('prunes with custom days option', function () {
    $log = MailLog::create([
        'subject' => 'Email 10 days old',
        'from' => [['email' => 'a@b.com', 'name' => '']],
        'to' => [['email' => 'c@d.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);
    MailLog::where('id', $log->id)->update(['created_at' => Carbon::now()->subDays(10)]);

    $this->artisan('mail:prune --days=7')
        ->expectsOutputToContain('Pruned 1 mail log(s)')
        ->assertSuccessful();

    expect(MailLog::count())->toBe(0);
});

it('does nothing when pruning is disabled', function () {
    config()->set('laravel-mail.prune.enabled', false);

    MailLog::create([
        'subject' => 'Old Email',
        'from' => [['email' => 'a@b.com', 'name' => '']],
        'to' => [['email' => 'c@d.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);

    $this->artisan('mail:prune')
        ->expectsOutputToContain('pruning is disabled')
        ->assertSuccessful();

    expect(MailLog::count())->toBe(1);
});

it('reports when no logs to prune', function () {
    $this->artisan('mail:prune')
        ->expectsOutputToContain('No mail logs to prune')
        ->assertSuccessful();
});

it('prunes per status policy with different retention days', function () {
    config()->set('laravel-mail.prune.policies', [
        'delivered' => 30,
        'bounced' => 90,
    ]);

    $delivered = MailLog::create([
        'subject' => 'Delivered 31 days ago',
        'from' => [['email' => 'a@b.com', 'name' => '']],
        'to' => [['email' => 'c@d.com', 'name' => '']],
        'status' => MailStatus::Delivered,
    ]);
    MailLog::where('id', $delivered->id)->update(['created_at' => Carbon::now()->subDays(31)]);

    $bounced = MailLog::create([
        'subject' => 'Bounced 31 days ago',
        'from' => [['email' => 'a@b.com', 'name' => '']],
        'to' => [['email' => 'c@d.com', 'name' => '']],
        'status' => MailStatus::Bounced,
    ]);
    MailLog::where('id', $bounced->id)->update(['created_at' => Carbon::now()->subDays(31)]);

    expect(MailLog::count())->toBe(2);

    $this->artisan('mail:prune')->assertSuccessful();

    // Delivered should be pruned (31 > 30), bounced should remain (31 < 90)
    expect(MailLog::count())->toBe(1)
        ->and(MailLog::first()->status)->toBe(MailStatus::Bounced);
});

it('falls back to default older_than_days when policies is null', function () {
    config()->set('laravel-mail.prune.policies', null);

    $log = MailLog::create([
        'subject' => 'Old Email',
        'from' => [['email' => 'a@b.com', 'name' => '']],
        'to' => [['email' => 'c@d.com', 'name' => '']],
        'status' => MailStatus::Sent,
    ]);
    MailLog::where('id', $log->id)->update(['created_at' => Carbon::now()->subDays(31)]);

    $this->artisan('mail:prune')
        ->expectsOutputToContain('Pruned 1 mail log(s)')
        ->assertSuccessful();
});
