<?php

namespace JeffersonGoncalves\LaravelMail\Enums;

enum TrackingProvider: string
{
    case Ses = 'ses';
    case SendGrid = 'sendgrid';
    case Postmark = 'postmark';
    case Mailgun = 'mailgun';
    case Resend = 'resend';
}
