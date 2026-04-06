<?php

namespace JeffersonGoncalves\LaravelMail\Events;

use JeffersonGoncalves\LaravelMail\Models\MailLog;
use JeffersonGoncalves\LaravelMail\Models\MailTrackingEvent;

class MailComplained
{
    public function __construct(
        public readonly MailLog $mailLog,
        public readonly MailTrackingEvent $trackingEvent,
    ) {}
}
