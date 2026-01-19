<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('good_moral_certificates', function (Blueprint $table) {
            if (!Schema::hasColumn('good_moral_certificates', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('updated_at');
            }

            if (!Schema::hasColumn('good_moral_certificates', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('good_moral_certificates', function (Blueprint $table) {
            if (Schema::hasColumn('good_moral_certificates', 'approved_at')) {
                $table->dropColumn('approved_at');
            }

            if (Schema::hasColumn('good_moral_certificates', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
        });
    }
};
