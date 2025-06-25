<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWorkPlaceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('viki_work_place', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->decimal('budget', 10, 3);

            $table->unsignedBigInteger('client_id');
            $table->foreign('client_id')->references('id')->on('viki_clients');

            $table->unsignedBigInteger('region_id');
            $table->foreign('region_id')->references('id')->on('viki_regions');

            $table->tinyInteger('status')->default(0);

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
        Schema::dropIfExists('viki_work_place');
    }
}
