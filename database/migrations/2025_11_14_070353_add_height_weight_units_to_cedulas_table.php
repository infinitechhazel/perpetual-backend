<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds unit fields for height and weight measurements
     * and ensures proper decimal precision for the values.
     */
    public function up(): void
    {
        Schema::table('cedulas', function (Blueprint $table) {
            // Change height and weight to decimal with precision (5 digits total, 2 after decimal)
            // This allows values from 0.01 to 999.99
            $table->decimal('height', 5, 2)->change();
            $table->decimal('weight', 5, 2)->change();
            
            // Add unit columns with default values
            $table->string('height_unit', 10)->default('cm')->after('height');
            $table->string('weight_unit', 10)->default('kg')->after('weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cedulas', function (Blueprint $table) {
            // Remove unit columns
            $table->dropColumn(['height_unit', 'weight_unit']);
            
            // Optionally revert decimal precision (adjust based on your original migration)
            $table->decimal('height', 8, 2)->change();
            $table->decimal('weight', 8, 2)->change();
        });
    }
};