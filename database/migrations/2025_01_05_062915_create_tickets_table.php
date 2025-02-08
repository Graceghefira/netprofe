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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id(); // Primary Key
            $table->string('tracking_id')->unique(); // ID pelacakan
            $table->date('hari_masuk'); // Hari Masuk
            $table->time('waktu_masuk'); // Waktu Masuk
            $table->date('hari_respon')->nullable(); // Hari Respon
            $table->time('waktu_respon')->nullable(); // Waktu Respon
            $table->string('nama_admin')->nullable(); // Nama Admin
            $table->string('email'); // Email Pengguna
            $table->string('category'); // Kategori Masalah
            $table->enum('priority', ['low', 'medium', 'high', 'critical']); // Prioritas
            $table->string('status'); // Status Tiket
            $table->string('subject'); // Judul Tiket
            $table->text('detail_kendala'); // Detail Kendala
            $table->string('owner'); // Pemilik Tiket
            $table->integer('time_worked')->nullable(); // Waktu Pengerjaan (dalam menit)
            $table->date('due_date')->nullable(); // Tanggal Jatuh Tempo
            $table->string('kategori_masalah')->nullable(); // Kategori Masalah Tambahan
            $table->text('respon_diberikan')->nullable(); // Respon yang Diberikan
            
            $table->timestamps(); // Created At & Updated At
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
