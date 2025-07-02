<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:users {file} {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate users from old Laravel 7 app to new Laravel 12 app';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');
        $dryRun = $this->option('dry-run');
        
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }
        
        $this->info('Starting user migration from: ' . $file);
        
        try {
            // Read SQL dump file
            $sql = file_get_contents($file);
            
            // Extract INSERT statements for users table
            preg_match_all('/INSERT INTO `users`[^;]+;/i', $sql, $matches);
            
            if (empty($matches[0])) {
                $this->error('No user INSERT statements found in the file');
                return 1;
            }
            
            $this->info('Found ' . count($matches[0]) . ' user INSERT statement(s)');
            
            foreach ($matches[0] as $insertStatement) {
                if ($dryRun) {
                    $this->line('Would execute: ' . substr($insertStatement, 0, 100) . '...');
                } else {
                    // Parse and execute the insert
                    $this->processUserInsert($insertStatement);
                }
            }
            
            if ($dryRun) {
                $this->info('Dry run completed. Use without --dry-run to actually import users.');
            } else {
                $this->info('User migration completed successfully!');
            }
            
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function processUserInsert($insertStatement)
    {
        // Extract VALUES from INSERT statement
        preg_match('/VALUES\s*\((.+)\)/i', $insertStatement, $matches);
        
        if (empty($matches[1])) {
            $this->warn('Could not parse INSERT statement');
            return;
        }
        
        $values = $matches[1];
        
        // Parse CSV values (this is a simplified parser)
        $fields = str_getcsv($values, ',', "'");
        
        // Assuming standard Laravel users table structure: id, name, email, password, created_at, updated_at
        // Skip if it's a test user (@example.com)
        if (isset($fields[2]) && strpos($fields[2], '@example.com') !== false) {
            $this->line('Skipping test user: ' . $fields[2]);
            return;
        }
        
        try {
            // Create user with new ID to avoid conflicts
            $user = \App\Models\User::create([
                'name' => trim($fields[1], "'"),
                'email' => trim($fields[2], "'"),
                'password' => trim($fields[3], "'"), // Already hashed from old app
                'created_at' => isset($fields[4]) ? trim($fields[4], "'") : now(),
                'updated_at' => isset($fields[5]) ? trim($fields[5], "'") : now(),
            ]);
            
            $this->info('Imported user: ' . $user->email . ' (ID: ' . $user->id . ')');
            
        } catch (\Exception $e) {
            $this->warn('Failed to import user: ' . $e->getMessage());
        }
    }
}
