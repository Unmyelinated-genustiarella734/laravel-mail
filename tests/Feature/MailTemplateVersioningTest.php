<?php

use JeffersonGoncalves\LaravelMail\Models\MailTemplate;
use JeffersonGoncalves\LaravelMail\Models\MailTemplateVersion;

it('creates version automatically on template creation', function () {
    $template = MailTemplate::create([
        'key' => 'auto-version-test',
        'name' => 'Test',
        'subject' => ['en' => 'Subject v1'],
        'html_body' => ['en' => '<p>Body v1</p>'],
    ]);

    expect(MailTemplateVersion::count())->toBe(1);

    $version = MailTemplateVersion::first();
    expect($version->mail_template_id)->toBe($template->id)
        ->and($version->version_number)->toBe(1)
        ->and($version->change_note)->toBe('Initial version')
        ->and($version->subject)->toBe(['en' => 'Subject v1']);
});

it('creates new version on content update', function () {
    $template = MailTemplate::create([
        'key' => 'version-update-test',
        'name' => 'Test',
        'subject' => ['en' => 'Subject v1'],
        'html_body' => ['en' => '<p>Body v1</p>'],
    ]);

    $template->update([
        'subject' => ['en' => 'Subject v2'],
        'html_body' => ['en' => '<p>Body v2</p>'],
    ]);

    expect(MailTemplateVersion::count())->toBe(2);

    $latest = MailTemplateVersion::orderBy('version_number', 'desc')->first();
    expect($latest->version_number)->toBe(2)
        ->and($latest->subject)->toBe(['en' => 'Subject v2'])
        ->and($latest->html_body)->toBe(['en' => '<p>Body v2</p>']);
});

it('does not create version for non-content changes', function () {
    $template = MailTemplate::create([
        'key' => 'no-version-test',
        'name' => 'Test',
        'subject' => ['en' => 'Subject'],
        'html_body' => ['en' => '<p>Body</p>'],
    ]);

    expect(MailTemplateVersion::count())->toBe(1);

    $template->update([
        'name' => 'Updated Name',
        'is_active' => false,
    ]);

    expect(MailTemplateVersion::count())->toBe(1);
});

it('increments version numbers correctly', function () {
    $template = MailTemplate::create([
        'key' => 'increment-test',
        'name' => 'Test',
        'subject' => ['en' => 'v1'],
        'html_body' => ['en' => '<p>v1</p>'],
    ]);

    $template->update(['subject' => ['en' => 'v2']]);
    $template->update(['subject' => ['en' => 'v3']]);

    $versions = MailTemplateVersion::orderBy('version_number')->pluck('version_number')->all();
    expect($versions)->toBe([1, 2, 3]);
});

it('snapshots text_body in version', function () {
    $template = MailTemplate::create([
        'key' => 'text-body-test',
        'name' => 'Test',
        'subject' => ['en' => 'Subject'],
        'html_body' => ['en' => '<p>HTML</p>'],
        'text_body' => ['en' => 'Plain text v1'],
    ]);

    $template->update(['text_body' => ['en' => 'Plain text v2']]);

    $versions = MailTemplateVersion::orderBy('version_number')->get();
    expect($versions)->toHaveCount(2)
        ->and($versions[0]->text_body)->toBe(['en' => 'Plain text v1'])
        ->and($versions[1]->text_body)->toBe(['en' => 'Plain text v2']);
});
