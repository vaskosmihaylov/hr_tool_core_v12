<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use viki\Service\Models\Elequent\WorkPlaceActivity;

class RevertEuroToLev extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'presence:revert-euro-to-lev
                            {--dry-run : Run without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revert currency conversion by multiplying all activity salaries by 1.96 (convert EURO back to LEV)';

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
        $this->info('║   REVERT: EURO → LEV Currency Conversion                 ║');
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
                ['Permanent Activities (templates - will be reverted)', $permanentActivities->count()],
                ['Monthly Snapshots 2026+ (will be reverted)', $monthlyActivities2026Plus->count()],
                ['Monthly Snapshots Pre-2026 (will NOT be changed)', $monthlyActivitiesPre2026],
            ]
        );

        $totalToRevert = $permanentActivities->count() + $monthlyActivities2026Plus->count();

        if ($totalToRevert === 0) {
            $this->info('✅ No activities found to revert.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('💰 Sample reversions (multiply by 1.96):');
        $this->newLine();

        // Combine both types for sampling
        $allActivitiesToRevert = $permanentActivities->merge($monthlyActivities2026Plus);
        $sampleActivities = $allActivitiesToRevert->take(5);
        $sampleData = [];

        foreach ($sampleActivities as $activity) {
            $currentEur = $activity->neto_salary;
            $revertedLev = round($currentEur * self::CONVERSION_RATE, 2);
            $type = $activity->date === null ? 'Permanent' : 'Monthly (' . $activity->date . ')';
            $sampleData[] = [
                $activity->id,
                $type,
                Str::limit($activity->activity, 20),
                number_format($currentEur, 2) . ' €',
                number_format($revertedLev, 2) . ' лв.',
            ];
        }

        $this->table(
            ['ID', 'Type', 'Activity Name', 'Current (EUR)', 'After Revert (LEV)'],
            $sampleData
        );

        $this->newLine();

        // Calculate total impact
        $totalCurrentEur = $allActivitiesToRevert->sum('neto_salary');
        $totalAfterLev = round($totalCurrentEur * self::CONVERSION_RATE, 2);

        $this->info("📈 Total Impact:");
        $this->line("   Current Total: " . number_format($totalCurrentEur, 2) . " €");
        $this->line("   After Reversion: " . number_format($totalAfterLev, 2) . " лв.");
        $this->newLine();

        // Warning and backup recommendation
        if (!$isDryRun) {
            $this->warn('⚠️  IMPORTANT NOTES:');
            $this->warn('   • This will permanently modify ' . $totalToRevert . ' activity records:');
            $this->warn('     - ' . $permanentActivities->count() . ' permanent activities (templates)');
            $this->warn('     - ' . $monthlyActivities2026Plus->count() . ' monthly snapshots (2026+)');
            $this->warn('   • Monthly snapshots before 2026 will NOT be affected');
            $this->warn('   • All values will be multiplied by 1.96 to restore LEV amounts');
            $this->newLine();

            $this->comment('💡 Recommendation: Create a database backup before proceeding');
            $this->newLine();
        }

        // Confirmation
        if (!$isDryRun && !$isForced) {
            if (!$this->confirm('Do you want to proceed with the reversion?', false)) {
                $this->info('❌ Reversion cancelled by user.');
                return Command::SUCCESS;
            }
            $this->newLine();
        }

        // Perform reversion
        if ($isDryRun) {
            $this->info('✅ Dry run completed. No changes were made.');
            $this->info('💡 Run without --dry-run to perform actual reversion.');
            return Command::SUCCESS;
        }

        $this->info('🔄 Starting reversion...');
        $this->newLine();

        // Combine all activities to revert
        $allActivitiesToRevert = $permanentActivities->merge($monthlyActivities2026Plus);

        $progressBar = $this->output->createProgressBar($allActivitiesToRevert->count());
        $progressBar->start();

        $revertedCount = 0;
        $revertedPermanent = 0;
        $revertedMonthly = 0;
        $failedCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($allActivitiesToRevert as $activity) {
                try {
                    $currentEur = $activity->neto_salary;
                    $revertedLev = round($currentEur * self::CONVERSION_RATE, 2);

                    $activity->neto_salary = $revertedLev;
                    $activity->save();

                    $revertedCount++;

                    // Track type of activity reverted
                    if ($activity->date === null) {
                        $revertedPermanent++;
                    } else {
                        $revertedMonthly++;
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
            $this->info('║                  Reversion Summary                        ║');
            $this->info('╚═══════════════════════════════════════════════════════════╝');
            $this->newLine();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['✅ Permanent Activities Reverted', $revertedPermanent],
                    ['✅ Monthly Snapshots (2026+) Reverted', $revertedMonthly],
                    ['✅ Total Successfully Reverted', $revertedCount],
                    ['❌ Failed', $failedCount],
                    ['📊 Total Processed', $allActivitiesToRevert->count()],
                ]
            );

            if ($failedCount > 0) {
                $this->newLine();
                $this->error('⚠️  Some reversions failed:');
                $this->table(
                    ['ID', 'Type', 'Activity', 'Error'],
                    $errors
                );
            }

            if ($revertedCount > 0) {
                $this->newLine();
                $this->info('✅ Currency reversion completed successfully!');
                $this->info('💰 Reverted activities:');
                $this->info('   • ' . $revertedPermanent . ' permanent activities (templates)');
                $this->info('   • ' . $revertedMonthly . ' monthly snapshots (2026+)');
                $this->info('🎯 All values restored to LEV amounts');
                $this->newLine();

                $this->comment('📝 Next steps:');
                $this->line('   1. Revert display changes (EUR → BGN labels)');
                $this->line('   2. Test monthly presence calculations');
                $this->line('   3. Verify worker salary displays');
                $this->line('   4. Check that all calculations work correctly');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $progressBar->finish();
            $this->newLine(2);

            $this->error('❌ Reversion failed and was rolled back!');
            $this->error('Error: ' . $e->getMessage());
            $this->newLine();

            return Command::FAILURE;
        }
    }
}
