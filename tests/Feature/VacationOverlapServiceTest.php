<?php

use App\Services\VacationOverlapService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::dropIfExists('viki_vacations');

    Schema::create('viki_vacations', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('worker_id');
        $table->unsignedTinyInteger('type')->default(1);
        $table->date('start_date');
        $table->date('end_date');
        $table->string('comment')->nullable();
        $table->unsignedInteger('day_count')->default(1);
        $table->unsignedTinyInteger('status')->default(1);
        $table->unsignedBigInteger('created_by')->nullable();
        $table->timestamps();
    });
});

it('finds vacations that duplicate or overlap existing vacation days', function () {
    $existingId = DB::table('viki_vacations')->insertGetId([
        'worker_id' => 7,
        'type' => 1,
        'start_date' => '2026-06-10',
        'end_date' => '2026-06-12',
        'status' => 1,
    ]);

    $service = app(VacationOverlapService::class);

    expect($service->findOverlap(7, '2026-06-10', '2026-06-12')?->id)->toBe($existingId)
        ->and($service->findOverlap(7, '2026-06-12', '2026-06-14')?->id)->toBe($existingId)
        ->and($service->findOverlap(7, '2026-06-07', '2026-06-09'))->toBeNull()
        ->and($service->findOverlap(7, '2026-06-13', '2026-06-15'))->toBeNull()
        ->and($service->findOverlap(8, '2026-06-10', '2026-06-12'))->toBeNull()
        ->and($service->findOverlap(7, '2026-06-10', '2026-06-12', $existingId))->toBeNull();
});

it('ignores refused vacation records when checking duplicate days', function () {
    DB::table('viki_vacations')->insert([
        'worker_id' => 7,
        'type' => 1,
        'start_date' => '2026-06-20',
        'end_date' => '2026-06-22',
        'status' => 2,
    ]);

    expect(app(VacationOverlapService::class)->findOverlap(7, '2026-06-20', '2026-06-22'))->toBeNull();
});
