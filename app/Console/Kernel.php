<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Publică postările ajunse la termen (deja ai comanda)
        $schedule->command('bot:publish-due')->everyFiveMinutes()->withoutOverlapping();

        // Auto-reply comentarii – la 5 minute
        $schedule->command('bot:auto-reply --since=7d')->everyFiveMinutes()->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
