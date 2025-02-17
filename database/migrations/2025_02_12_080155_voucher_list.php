<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('voucher_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('password')->unique()->nullable();
            $table->string('profile')->nullable();
            $table->integer('waktu')->nullable();
            $table->string('status')->default('belum digunakan');
            $table->string('link_login')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('voucher_lists');
    }
};
