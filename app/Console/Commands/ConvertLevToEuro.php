<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use viki\Service\Models\Elequent\WorkPlaceActivity;

class ConvertLevToEuro extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'presence:convert-lev-to-euro
                            {--dry-run : Run without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert all permanent activity salaries from LEV to EURO (divide by 1.96) for Bulgaria\'s Euro adoption in January 2026';

    /**
     * Conversion rate: 1 EUR = 1.96 BGN
     */
    const CONVERSION_RATE = 1.96;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('╔═══════════════════════════════════════════════════════════╗');
        $this->info('║   LEV → EURO Currency Conversion for Bulgaria 2026       ║');
        $this->info('╚═══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get statistics
        $this->info('📊 Analyzing database...');
        $this->newLine();

        $permanentActivities = WorkPlaceActivity::whereNull('date')->get();
        $monthlyActivities2026Plus = WorkPlaceActivity::whereNotNull('date')
            ->where('date', '>=', '2026-01-01')
            ->get();
        $monthlyActivitiesPre2026 = WorkPlaceActivity::whereNotNull('date')
            ->where('date', '<', '2026-01-01')
            ->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Permanent Activities (templates - will be converted)', $permanentActivities->count()],
                ['Monthly Snapshots 2026+ (will be converted)', $monthlyActivities2026Plus->count()],
                ['Monthly Snapshots Pre-2026 (will NOT be converted)', $monthlyActivitiesPre2026],
            ]
        );

        $totalToConvert = $permanentActivities->count() + $monthlyActivities2026Plus->count();

        if ($totalToConvert === 0) {
            $this->info('✅ No activities found to convert.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('💰 Sample conversions:');
        $this->newLine();

        // Combine both types for sampling
        $allActivitiesToConvert = $permanentActivities->merge($monthlyActivities2026Plus);
        $sampleActivities = $allActivitiesToConvert->take(5);
        $sampleData = [];

        foreach ($sampleActivities as $activity) {
            $oldSalary = $activity->neto_salary;
            $newSalary = round($oldSalary / self::CONVERSION_RATE, 2);
            $type = $activity->date === null ? 'Permanent' : 'Monthly (' . $activity->date . ')';
            $sampleData[] = [
                $activity->id,
                $type,
                Str::limit($activity->activity, 20),
                number_format($oldSalary, 2) . ' лв.',
                number_format($newSalary, 2) . ' €',
            ];
        }

        $this->table(
            ['ID', 'Type', 'Activity Name', 'Current (LEV)', 'After (EUR)'],
            $sampleData
        );

        $this->newLine();

        // Calculate total impact
        $totalCurrentLev = $allActivitiesToConvert->sum('neto_salary');
        $totalAfterEur = round($totalCurrentLev / self::CONVERSION_RATE, 2);

        $this->info("📈 Total Impact:");
        $this->line("   Current Total: " . number_format($totalCurrentLev, 2) . " лв.");
        $this->line("   After Conversion: " . number_format($totalAfterEur, 2) . " €");
        $this->newLine();

        // Warning and backup recommendation
        if (!$isDryRun) {
            $this->warn('⚠️  IMPORTANT NOTES:');
            $this->warn('   • This will permanently modify ' . $totalToConvert . ' activity records:');
            $this->warn('     - ' . $permanentActivities->count() . ' permanent activities (templates)');
            $this->warn('     - ' . $monthlyActivities2026Plus->count() . ' monthly snapshots (2026+)');
            $this->warn('   • Monthly snapshots before 2026 will NOT be affected');
            $this->warn('   • All future monthly calculations will use EUR values');
            $this->warn('   • Historical data (Dec 2025 and earlier) remains in old app');
            $this->newLine();

            $this->comment('💡 Recommendation: Create a database backup before proceeding');
            $this->newLine();
        }

        // Confirmation
        if (!$isDryRun && !$isForced) {
            if (!$this->confirm('Do you want to proceed with the conversion?', false)) {
                $this->info('❌ Conversion cancelled by user.');
                return Command::SUCCESS;
            }
            $this->newLine();
        }

        // Perform conversion
        if ($isDryRun) {
            $this->info('✅ Dry run completed. No changes were made.');
            $this->info('💡 Run without --dry-run to perform actual conversion.');
            return Command::SUCCESS;
        }

        $this->info('🔄 Starting conversion...');
        $this->newLine();

        // Combine all activities to convert
        $allActivitiesToConvert = $permanentActivities->merge($monthlyActivities2026Plus);

        $progressBar = $this->output->createProgressBar($allActivitiesToConvert->count());
        $progressBar->start();

        $convertedCount = 0;
        $convertedPermanent = 0;
        $convertedMonthly = 0;
        $failedCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($allActivitiesToConvert as $activity) {
                try {
                    $oldSalary = $activity->neto_salary;
                    $newSalary = round($oldSalary / self::CONVERSION_RATE, 2);

                    $activity->neto_salary = $newSalary;
                    $activity->save();

                    $convertedCount++;

                    // Track type of activity converted
                    if ($activity->date === null) {
                        $convertedPermanent++;
                    } else {
                        $convertedMonthly++;
                    }

                    $progressBar->advance();
                } catch (\Exception $e) {
                    $failedCount++;
                    $activityType = $activity->date === null ? 'Permanent' : 'Monthly (' . $activity->date . ')';
                    $errors[] = [
                        'id' => $activity->id,
                        'type' => $activityType,
                        'activity' => Str::limit($activity->activity, 30),
                        'error' => $e->getMessage(),
                    ];
                    $progressBar->advance();
                }
            }

            DB::commit();
            $progressBar->finish();
            $this->newLine(2);

            // Summary
            $this->info('╔═══════════════════════════════════════════════════════════╗');
            $this->info('║                  Conversion Summary                       ║');
            $this->info('╚═══════════════════════════════════════════════════════════╝');
            $this->newLine();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['✅ Permanent Activities Converted', $convertedPermanent],
                    ['✅ Monthly Snapshots (2026+) Converted', $convertedMonthly],
                    ['✅ Total Successfully Converted', $convertedCount],
                    ['❌ Failed', $failedCount],
                    ['📊 Total Processed', $allActivitiesToConvert->count()],
                ]
            );

            if ($failedCount > 0) {
                $this->newLine();
                $this->error('⚠️  Some conversions failed:');
                $this->table(
                    ['ID', 'Type', 'Activity', 'Error'],
                    $errors
                );
            }

            if ($convertedCount > 0) {
                $this->newLine();
                $this->info('✅ Currency conversion completed successfully!');
                $this->info('💶 Converted activities:');
                $this->info('   • ' . $convertedPermanent . ' permanent activities (templates)');
                $this->info('   • ' . $convertedMonthly . ' monthly snapshots (2026+)');
                $this->info('🎯 All 2026+ monthly presence calculations will now use EUR values');
                $this->newLine();

                $this->comment('📝 Next steps:');
                $this->line('   1. Check monthly presence for January 2026 - should show EUR values');
                $this->line('   2. Verify "Сумарно" worker calculations use EUR');
                $this->line('   3. Test creating new monthly activities - should use EUR');
                $this->line('   4. Ensure all displays show € instead of лв.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $progressBar->finish();
            $this->newLine(2);

            $this->error('❌ Conversion failed and was rolled back!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();

            return Command::FAILURE;
        }
    }
}
