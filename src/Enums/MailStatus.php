<?php

namespace JeffersonGoncalves\LaravelMail\Enums;

enum MailStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Failed = 'failed';
}
