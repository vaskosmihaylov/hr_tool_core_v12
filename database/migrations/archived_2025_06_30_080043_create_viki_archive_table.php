<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viki_archive', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_place_id');
            $table->date('date');
            $table->longText('json_data');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('work_place_id')->references('id')->on('viki_work_place')->onDelete('cascade');

            // Index for better performance on queries
            $table->index(['work_place_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viki_archive');
    }
};
