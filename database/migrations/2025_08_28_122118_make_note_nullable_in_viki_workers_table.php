<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Just disable strict mode temporarily to make the note field nullable
        DB::statement("SET sql_mode = ''");
        
        Schema::table('viki_workers', function (Blueprint $table) {
            $table->string('note')->nullable()->change();
        });
        
        // Convert empty strings to nulls for existing records
        DB::statement("UPDATE viki_workers SET note = NULL WHERE note = ''");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viki_workers', function (Blueprint $table) {
            $table->string('note')->nullable(false)->change();
        });
    }
};
