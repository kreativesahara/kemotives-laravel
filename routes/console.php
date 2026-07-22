<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:check-expired')->dailyAt('12:00');
// Schedule::command('users:delete-expired-content')->dailyAt('13:00');
// Schedule::command('products:manage-lifecycle')->dailyAt('14:00');

// Queue worker for shared hosting (cPanel)
// Runs every minute, processes pending jobs, and exits before the next minute starts
Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
