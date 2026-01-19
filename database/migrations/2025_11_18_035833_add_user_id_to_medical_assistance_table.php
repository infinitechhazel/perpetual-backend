<?php

// ============================================
// FILE: database/migrations/2024_11_18_000001_add_user_id_to_medical_assistance_table.php
// ============================================

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
        Schema::table('medical_assistances', function (Blueprint $table) {
            // Check if user_id column doesn't exist before adding
            if (!Schema::hasColumn('medical_assistances', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_assistances', function (Blueprint $table) {
            if (Schema::hasColumn('medical_assistances', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }
};