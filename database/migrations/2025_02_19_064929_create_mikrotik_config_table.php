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
        Schema::create('mikrotik_config', function (Blueprint $table) {
            $table->id();
            $table->string('host');
            $table->string('user');
            $table->string('pass');
            $table->integer('port')->default(8728);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_config');
    }
};
