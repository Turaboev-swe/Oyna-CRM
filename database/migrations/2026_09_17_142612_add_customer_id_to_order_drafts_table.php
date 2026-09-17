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
            $table->foreignId('customer_id')->nullable()->after('height_meters')->constrained('customers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_drafts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
