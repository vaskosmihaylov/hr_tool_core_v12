<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test user permissions for service routes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $supervisor = \App\Models\User::where('email', 'supervisor@example.com')->first();
        $hr = \App\Models\User::where('email', 'hr@example.com')->first();
        
        if (!$supervisor || !$hr) {
            $this->error('Test users not found');
            return;
        }
        
        $testUrls = [
            'service/region',
            'service/client', 
            'service/worker/create',
            'service/archive',
            'service/presence',
            'service/approvement'
        ];
        
        $this->info('Testing permission system after resource mapping fix:');
        $this->line('');
        
        foreach ($testUrls as $url) {
            $supervisorAccess = $supervisor->hasPermissionUrl($url);
            $hrAccess = $hr->hasPermissionUrl($url);
            
            $this->line(sprintf(
                '%-25s | Supervisor: %s | HR: %s',
                $url,
                $supervisorAccess ? '✅ YES' : '❌ NO',
                $hrAccess ? '✅ YES' : '❌ NO'
            ));
        }
        
        $this->line('');
        $this->info('Testing complete!');
    }
}
