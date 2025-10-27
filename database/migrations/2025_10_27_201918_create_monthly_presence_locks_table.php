<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('viki_monthly_presence_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_place_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->boolean('is_locked')->default(true);
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('unlocked_by')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamps();

            // Unique constraint: one lock record per workplace/month combination
            $table->unique(['work_place_id', 'year', 'month']);

            // Index for faster lookups
            $table->index(['work_place_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viki_monthly_presence_locks');
    }
};
