<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cms:publish-due')->everyMinute();

if (class_exists(\TestoCms\Booking\Console\BookingMaintenanceCommand::class)) {
    Schedule::command('booking:maintenance')->everyFiveMinutes();
}
