<?php

namespace JeffersonGoncalves\LaravelMail\Enums;

enum SuppressionReason: string
{
    case HardBounce = 'hard_bounce';
    case Complaint = 'complaint';
    case Manual = 'manual';
}
