<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\View\Composers\UnviewedApplicationsComposer;
use App\View\Composers\UnreadMessagesComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL; 
use Illuminate\Support\Facades\Mail;
use App\Mail\BrevoTransport;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('brevo', function (array $config) {
            return new BrevoTransport(
                config('services.brevo.key')
            );
        });
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
        View::composer('admin.components.sidebar', UnviewedApplicationsComposer::class);
        View::composer('admin.components.sidebar', UnreadMessagesComposer::class);

    }
}
