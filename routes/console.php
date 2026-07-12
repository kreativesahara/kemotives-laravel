<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('subscriptions:check-expired')->dailyAt('12:00');
Schedule::command('users:delete-expired-content')->dailyAt('13:00');
Schedule::command('products:manage-lifecycle')->dailyAt('14:00');
