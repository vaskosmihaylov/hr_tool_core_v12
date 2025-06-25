<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('viki_workers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('middle_name');
            $table->string('family_name');
            $table->string('egn', 10)->unique();
            $table->date('start_date');
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('type_working')->default(0);
            $table->integer('hours_per_day')->unsigned();

            $table->unsignedBigInteger('work_place_id');
            $table->foreign('work_place_id')->references('id')->on('viki_work_place');

            $table->unsignedBigInteger('region_id');
            $table->foreign('region_id')->references('id')->on('viki_regions');

            $table->decimal('neto_salary', 9, 3);
            $table->string('note');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('viki_workers');
    }
}
