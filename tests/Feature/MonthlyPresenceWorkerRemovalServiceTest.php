<?php

use App\Services\Presence\MonthlyPresenceWorkerRemovalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('viki_worker_records');
    Schema::dropIfExists('viki_work_place_activity_worker');
    Schema::dropIfExists('viki_work_place_worker');
    Schema::dropIfExists('viki_work_place_activity');

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

    Schema::create('viki_work_place_activity_worker', function (Blueprint $table) {
        $table->unsignedBigInteger('work_place_activity_id');
        $table->unsignedBigInteger('worker_id');
        $table->date('date');
    });

    Schema::create('viki_work_place_worker', function (Blueprint $table) {
        $table->unsignedBigInteger('work_place_id');
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
        $table->unsignedBigInteger('creator_id')->nullable();
        $table->timestamps();
    });
});

it('removes a worker only from the selected activity when they are assigned to multiple activities in the month', function () {
    seedMonthlyPresenceAssignments();

    app(MonthlyPresenceWorkerRemovalService::class)->removeWorkerFromActivity(
        workplaceId: 190,
        activityId: 1001,
        workerId: 501,
        month: '2026-05-01',
    );

    expect(DB::table('viki_work_place_activity_worker')
        ->where('work_place_activity_id', 1001)
        ->where('worker_id', 501)
        ->where('date', '2026-05-01')
        ->exists())->toBeFalse()
        ->and(DB::table('viki_work_place_activity_worker')
            ->where('work_place_activity_id', 1002)
            ->where('worker_id', 501)
            ->where('date', '2026-05-01')
            ->exists())->toBeTrue()
        ->and(DB::table('viki_work_place_worker')
            ->where('work_place_id', 190)
            ->where('worker_id', 501)
            ->where('date', '2026-05-01')
            ->exists())->toBeTrue()
        ->and(DB::table('viki_worker_records')
            ->where('work_place_activity_id', 1001)
            ->whereBetween('date', ['2026-05-01', '2026-05-31'])
            ->exists())->toBeFalse()
        ->and(DB::table('viki_worker_records')
            ->where('work_place_activity_id', 1002)
            ->whereBetween('date', ['2026-05-01', '2026-05-31'])
            ->exists())->toBeTrue()
        ->and(DB::table('viki_worker_records')
            ->where('work_place_activity_id', 1001)
            ->where('date', '2026-06-01')
            ->exists())->toBeTrue();
});

it('removes the workplace-month assignment only when no activity assignments remain for that workplace month', function () {
    seedMonthlyPresenceAssignments();

    app(MonthlyPresenceWorkerRemovalService::class)->removeWorkerFromActivity(
        workplaceId: 190,
        activityId: 1001,
        workerId: 501,
        month: '2026-05-01',
    );

    app(MonthlyPresenceWorkerRemovalService::class)->removeWorkerFromActivity(
        workplaceId: 190,
        activityId: 1002,
        workerId: 501,
        month: '2026-05-01',
    );

    expect(DB::table('viki_work_place_worker')
        ->where('work_place_id', 190)
        ->where('worker_id', 501)
        ->where('date', '2026-05-01')
        ->exists())->toBeFalse()
        ->and(DB::table('viki_work_place_worker')
            ->where('work_place_id', 191)
            ->where('worker_id', 501)
            ->where('date', '2026-05-01')
            ->exists())->toBeTrue();
});

function seedMonthlyPresenceAssignments(): void
{
    DB::table('viki_work_place_activity')->insert([
        [
            'id' => 1001,
            'work_place_id' => 190,
            'activity' => 'васко тест',
            'copied' => 0,
            'type_working' => 1,
            'neto_salary' => 745,
            'worker_count' => 1,
            'date' => null,
        ],
        [
            'id' => 1002,
            'work_place_id' => 190,
            'activity' => 'Хигиенист 8ч 745 EUR',
            'copied' => 0,
            'type_working' => 1,
            'neto_salary' => 745,
            'worker_count' => 1,
            'date' => null,
        ],
        [
            'id' => 2001,
            'work_place_id' => 191,
            'activity' => 'Other workplace',
            'copied' => 0,
            'type_working' => 1,
            'neto_salary' => 745,
            'worker_count' => 1,
            'date' => null,
        ],
    ]);

    DB::table('viki_work_place_activity_worker')->insert([
        ['work_place_activity_id' => 1001, 'worker_id' => 501, 'date' => '2026-05-01'],
        ['work_place_activity_id' => 1002, 'worker_id' => 501, 'date' => '2026-05-01'],
        ['work_place_activity_id' => 2001, 'worker_id' => 501, 'date' => '2026-05-01'],
    ]);

    DB::table('viki_work_place_worker')->insert([
        ['work_place_id' => 190, 'worker_id' => 501, 'date' => '2026-05-01'],
        ['work_place_id' => 191, 'worker_id' => 501, 'date' => '2026-05-01'],
    ]);

    DB::table('viki_worker_records')->insert([
        [
            'worker_id' => 501,
            'work_place_id' => 190,
            'work_place_activity_id' => 1001,
            'date' => '2026-05-05',
            'hours' => 4,
        ],
        [
            'worker_id' => 501,
            'work_place_id' => 190,
            'work_place_activity_id' => 1002,
            'date' => '2026-05-05',
            'hours' => 4,
        ],
        [
            'worker_id' => 501,
            'work_place_id' => 190,
            'work_place_activity_id' => 1001,
            'date' => '2026-06-01',
            'hours' => 4,
        ],
        [
            'worker_id' => 501,
            'work_place_id' => 191,
            'work_place_activity_id' => 2001,
            'date' => '2026-05-05',
            'hours' => 4,
        ],
    ]);
}
