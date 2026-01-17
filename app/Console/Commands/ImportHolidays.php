<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use viki\Service\Models\Elequent\SpecialDay;

class ImportHolidays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'holidays:import {year?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import holidays from the API for the specified year (defaults to current year)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $year = $this->argument('year') ?? date('Y');

        $this->info("Importing holidays for year {$year}...");

        try {
            $result = SpecialDay::insertHolidays($year);

            $this->info("✅ {$result}");

            // Count imported holidays for this year
            $count = SpecialDay::where('date', 'LIKE', "{$year}-%")
                ->where('type', SpecialDay::NO_WORKING)
                ->count();

            $this->line("Total holidays in database for {$year}: {$count}");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to import holidays: " . $e->getMessage());
            return 1;
        }
    }
}
