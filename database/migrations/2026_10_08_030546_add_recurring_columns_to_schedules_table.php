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
        Schema::table('schedules', function (Blueprint $table) {
            $table->uuid('series_id')->nullable()->index();

            $table->string('repeat_type', 20)->nullable();

            $table->date('repeat_until')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['series_id']);
            $table->dropColumn(['series_id', 'repeat_type', 'repeat_until']);
        });
    }
};
