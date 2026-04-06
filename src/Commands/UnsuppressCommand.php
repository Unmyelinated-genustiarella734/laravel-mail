<?php

namespace JeffersonGoncalves\LaravelMail\Commands;

use Illuminate\Console\Command;
use JeffersonGoncalves\LaravelMail\Models\MailSuppression;

class UnsuppressCommand extends Command
{
    protected $signature = 'mail:unsuppress {email : The email address to unsuppress}';

    protected $description = 'Remove an email address from the suppression list';

    public function handle(): int
    {
        $email = $this->argument('email');

        $modelClass = config('laravel-mail.models.mail_suppression', MailSuppression::class);

        $count = $modelClass::where('email', $email)->delete();

        if ($count === 0) {
            $this->components->warn("Email {$email} was not found in the suppression list.");

            return self::SUCCESS;
        }

        $this->components->info("Removed {$email} from the suppression list.");

        return self::SUCCESS;
    }
}
