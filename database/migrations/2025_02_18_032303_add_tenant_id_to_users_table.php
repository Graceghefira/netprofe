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
        Schema::table('users', function (Blueprint $table) {
            $table->string('tenant_id')->nullable();  // Add tenant_id column
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');  // Add foreign key constraint
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);  // Drop the foreign key
            $table->dropColumn('tenant_id');  // Drop the tenant_id column
        });
    }
};
