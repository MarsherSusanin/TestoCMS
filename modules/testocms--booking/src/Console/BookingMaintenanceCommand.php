<?php

namespace TestoCms\Booking\Console;

use Illuminate\Console\Command;
use TestoCms\Booking\Services\BookingBookingWorkflowService;
use TestoCms\Booking\Services\BookingSlotProjectionService;

class BookingMaintenanceCommand extends Command
{
    protected $signature = 'booking:maintenance';

    protected $description = 'Expire pending booking holds and rebuild booking slot projections';

    public function __construct(
        private readonly BookingBookingWorkflowService $workflow,
        private readonly BookingSlotProjectionService $projection,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = $this->workflow->expireRequestedHolds();
        $projected = $this->projection->rebuildAll();

        $this->info(sprintf('Expired holds: %d | Rebuilt slots: %d', $expired, $projected));

        return self::SUCCESS;
    }
}
