<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

Schedule::command('bot:publish-due')->everyMinute();
Schedule::command('bot:auto-reply --since-minutes=15')->everyFiveMinutes();
