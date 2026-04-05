<?php

namespace JeffersonGoncalves\LaravelMail\Actions;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use JeffersonGoncalves\LaravelMail\Models\MailLog;

class ResendMailAction
{
    public function execute(MailLog $mailLog): void
    {
        $mailer = $mailLog->mailer ?? config('mail.default');

        Mail::mailer($mailer)->send([], [], function (Message $message) use ($mailLog) {
            $message->subject($mailLog->subject ?? '');

            if ($mailLog->from) {
                foreach ($mailLog->from as $address) {
                    $message->from($address['email'], $address['name']);
                }
            }

            if ($mailLog->to) {
                foreach ($mailLog->to as $address) {
                    $message->to($address['email'], $address['name']);
                }
            }

            if ($mailLog->cc) {
                foreach ($mailLog->cc as $address) {
                    $message->cc($address['email'], $address['name']);
                }
            }

            if ($mailLog->bcc) {
                foreach ($mailLog->bcc as $address) {
                    $message->bcc($address['email'], $address['name']);
                }
            }

            if ($mailLog->reply_to) {
                foreach ($mailLog->reply_to as $address) {
                    $message->replyTo($address['email'], $address['name']);
                }
            }

            $symfony = $message->getSymfonyMessage();

            if ($mailLog->html_body) {
                $symfony->html($mailLog->html_body);
            }

            if ($mailLog->text_body) {
                $symfony->text($mailLog->text_body);
            }
        });
    }
}
