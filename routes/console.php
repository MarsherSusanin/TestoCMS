<?php

use Illuminate\Support\Facades\Schedule;
use TestoCms\Booking\Console\BookingMaintenanceCommand;

Schedule::command('cms:publish-due')->everyMinute();

if (class_exists(BookingMaintenanceCommand::class)) {
    Schedule::command('booking:maintenance')->everyFiveMinutes();
}
