<?php

namespace JeffersonGoncalves\LaravelMail\Enums;

enum TrackingEventType: string
{
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Complained = 'complained';
    case Deferred = 'deferred';
}
