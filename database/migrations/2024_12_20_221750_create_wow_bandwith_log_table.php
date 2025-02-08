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
        Schema::create('wow_bandwith_log', function (Blueprint $table) {
            $table->id();
            $table->string('interface_name') ;
            $table->string('role')->nullable();  // Username associated with the bytes
            $table->bigInteger('bytes_in')->default(0);
            $table->bigInteger('bytes_out')->default(0);
            $table->timestamp('timestamp')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wow_bandwith_log');
    }
};
