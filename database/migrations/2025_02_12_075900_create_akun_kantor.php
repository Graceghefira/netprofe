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
        Schema::create('akun_kantor', function (Blueprint $table) {
            $table->id();
            $table->string('no_hp', 20);
            $table->string('name', 255);
            $table->string('profile', 50);
            $table->timestamps(); // Ini menambahkan kolom created_at dan updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('akun_kantor');
    }
};
