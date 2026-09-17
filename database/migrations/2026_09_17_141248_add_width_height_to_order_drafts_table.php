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
        Schema::table('order_drafts', function (Blueprint $table) {
            $table->decimal('width_meters', 8, 2)->nullable()->after('square_meters');
            $table->decimal('height_meters', 8, 2)->nullable()->after('width_meters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_drafts', function (Blueprint $table) {
            $table->dropColumn(['width_meters', 'height_meters']);
        });
    }
};
