<?php

use App\Filament\Service\Resources\PresenceResource\Pages\MonthlyPresence;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use viki\Service\Models\Elequent\WorkPlaceActivity;

beforeEach(function () {
    Schema::dropIfExists('viki_hours_activity_by_month');
    Schema::dropIfExists('viki_hours_per_day_activity');
    Schema::dropIfExists('viki_worker_records');
    Schema::dropIfExists('viki_work_place_activity_worker');
    Schema::dropIfExists('viki_workers');
    Schema::dropIfExists('viki_work_place_activity_month_snapshots');
    Schema::dropIfExists('viki_monthly_presence_locks');
    Schema::dropIfExists('viki_work_place_activity');
    Schema::dropIfExists('viki_work_place');
    Schema::dropIfExists('viki_special_days');
    Schema::dropIfExists('viki_clients');
    Schema::dropIfExists('viki_regions');
    Schema::dropIfExists('users');

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('viki_regions', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('viki_clients', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('viki_work_place', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->decimal('budget', 10, 3)->default(0);
        $table->unsignedBigInteger('client_id')->nullable();
        $table->unsignedBigInteger('region_id')->nullable();
        $table->tinyInteger('status')->default(0);
        $table->timestamps();
    });

    Schema::create('viki_work_place_activity', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('work_place_id');
        $table->string('activity');
        $table->unsignedTinyInteger('copied')->default(0);
        $table->unsignedTinyInteger('type_working')->default(1);
        $table->decimal('neto_salary', 10, 2)->default(0);
        $table->unsignedInteger('worker_count')->default(0);
        $table->date('date')->nullable();
        $table->timestamps();
    });

    Schema::create('viki_monthly_presence_locks', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('work_place_id');
        $table->unsignedSmallInteger('year');
        $table->unsignedTinyInteger('month');
        $table->boolean('is_locked')->default(true);
        $table->timestamps();
    });

    Schema::create('viki_work_place_activity_month_snapshots', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('work_place_id');
        $table->unsignedBigInteger('base_activity_id');
        $table->date('date');
        $table->string('activity');
        $table->unsignedTinyInteger('type_working')->default(0);
        $table->decimal('neto_salary', 12, 4)->default(0);
        $table->unsignedInteger('worker_count')->default(1);
        $table->decimal('hours_per_day', 8, 2)->nullable();
        $table->timestamps();
    });

    Schema::create('viki_workers', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('middle_name')->nullable();
        $table->string('family_name')->nullable();
        $table->tinyInteger('status')->default(0);
        $table->timestamps();
    });

    Schema::create('viki_work_place_activity_worker', function (Blueprint $table) {
        $table->unsignedBigInteger('work_place_activity_id');
        $table->unsignedBigInteger('worker_id');
        $table->date('date');
    });

    Schema::create('viki_worker_records', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('worker_id');
        $table->unsignedBigInteger('work_place_id');
        $table->unsignedBigInteger('work_place_activity_id')->nullable();
        $table->date('date');
        $table->decimal('hours', 8, 2)->default(0);
        $table->unsignedTinyInteger('status')->default(0);
        $table->timestamps();
    });

    Schema::create('viki_hours_per_day_activity', function (Blueprint $table) {
        $table->id();
        $table->decimal('hours_per_day', 8, 2)->default(0);
        $table->unsignedBigInteger('work_place_activity_id');
        $table->timestamps();
    });

    Schema::create('viki_hours_activity_by_month', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('work_place_activity_id');
        $table->decimal('hours_for_person', 8, 2)->default(0);
        $table->date('date');
        $table->timestamps();
    });

    Schema::create('viki_special_days', function (Blueprint $table) {
        $table->id();
        $table->date('date');
        $table->unsignedTinyInteger('type')->default(1);
        $table->string('comment')->nullable();
    });
});

it('loads hourly monthly presence when configured monthly hours are zero', function () {
    DB::table('users')->insert([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@example.test',
        'password' => 'password',
    ]);

    DB::table('viki_work_place')->insert([
        'id' => 318,
        'name' => 'Фохар',
        'budget' => 0,
        'status' => 0,
    ]);

    DB::table('viki_work_place_activity')->insert([
        'id' => 9001,
        'work_place_id' => 318,
        'activity' => 'Фохар сумарно',
        'copied' => WorkPlaceActivity::NOT_COPIED_ACTIVITY,
        'type_working' => WorkPlaceActivity::WORKING_BY_HOURS,
        'neto_salary' => 1200,
        'worker_count' => 1,
        'date' => null,
    ]);

    DB::table('viki_hours_activity_by_month')->insert([
        'work_place_activity_id' => 9001,
        'hours_for_person' => 0,
        'date' => '2026-06-01',
    ]);

    $admin = new class extends User {
        public function hasRole($roles, ?string $guard = null): bool
        {
            return is_array($roles)
                ? in_array('admin', $roles, true)
                : $roles === 'admin';
        }
    };
    $admin->forceFill([
        'id' => 1,
        'name' => 'Admin',
        'email' => 'admin@example.test',
    ]);

    $this->actingAs($admin);

    $page = app(MonthlyPresence::class);
    $page->mount(318, '06-2026');

    $activity = $page->monthlyData->get(9001);

    expect($activity['hour_rate'])->toBe(0.0)
        ->and($activity['group_totals']['max_hours'])->toBe(0.0);
});
