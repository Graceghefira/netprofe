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
        Schema::create('user_bytes_log', function (Blueprint $table) {
            $table->id();  // Primary key with auto-increment
            $table->string('user_name');  // Username associated with the bytes
            $table->string('role');  // Username associated with the bytes
            $table->bigInteger('bytes_in')->default(0);  // Bytes-in for the user
            $table->bigInteger('bytes_out')->default(0);  // Bytes-out for the user
            $table->timestamp('timestamp')->useCurrent();  // When the record was created
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_bytes_log');
    }
};
