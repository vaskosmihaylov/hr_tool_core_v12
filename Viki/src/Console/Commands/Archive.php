<?php

namespace viki\Service\Console\Commands;

use Illuminate\Console\Command;

class Archive extends Command
{
    protected $signature = 'archive:start';
    protected $description = 'Create archive for older months (placeholder)';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Archive command placeholder - will be implemented in Step 4');
        return 0;
    }
}
