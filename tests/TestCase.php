<?php

namespace JeffersonGoncalves\LaravelMail\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use JeffersonGoncalves\LaravelMail\LaravelMailServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'JeffersonGoncalves\\LaravelMail\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelMailServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $this->runMigrations();
    }

    protected function runMigrations(): void
    {
        $migrations = [
            'create_mail_logs_table',
            'create_mail_templates_table',
            'create_mail_template_versions_table',
            'create_mail_tracking_events_table',
        ];

        foreach ($migrations as $migration) {
            $migrationFile = __DIR__.'/../database/migrations/'.$migration.'.php.stub';
            $migration = include $migrationFile;
            $migration->up();
        }
    }
}
