<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resources = [
            ['id' => 1, 'type' => 1, 'value' => 'service/worker/create', 'permission_id' => 1],
            ['id' => 2, 'type' => 1, 'value' => 'service/worker/vacation/', 'permission_id' => 2],
            ['id' => 3, 'type' => 1, 'value' => 'service/region/create', 'permission_id' => 3],
            ['id' => 4, 'type' => 1, 'value' => 'service/client/create', 'permission_id' => 4],
            ['id' => 5, 'type' => 1, 'value' => 'service/workplace/create', 'permission_id' => 5],
            ['id' => 6, 'type' => 1, 'value' => 'service/workplace/activity', 'permission_id' => 6],
            ['id' => 7, 'type' => 1, 'value' => 'service/region/edit/', 'permission_id' => 7],
            ['id' => 8, 'type' => 1, 'value' => 'service/users/create', 'permission_id' => 8],
            ['id' => 9, 'type' => 1, 'value' => 'service/region', 'permission_id' => 9],
            ['id' => 10, 'type' => 1, 'value' => 'service/client', 'permission_id' => 10],
            ['id' => 11, 'type' => 1, 'value' => 'service/users', 'permission_id' => 11],
            ['id' => 12, 'type' => 1, 'value' => 'service/workplace', 'permission_id' => 12],
            ['id' => 13, 'type' => 1, 'value' => 'service/worker/insert_holidays', 'permission_id' => 13],
            ['id' => 14, 'type' => 1, 'value' => 'service/history', 'permission_id' => 14],
            ['id' => 15, 'type' => 1, 'value' => 'service/approvement', 'permission_id' => 15],
            ['id' => 16, 'type' => 1, 'value' => 'service/presence', 'permission_id' => 16],
            ['id' => 17, 'type' => 1, 'value' => 'service/archive', 'permission_id' => 17],
            ['id' => 18, 'type' => 2, 'value' => '/register', 'permission_id' => 18],
        ];

        DB::table('resources')->insert($resources);
    }
}
