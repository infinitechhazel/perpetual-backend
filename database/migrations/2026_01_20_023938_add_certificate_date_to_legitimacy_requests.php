<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legitimacy_requests', function (Blueprint $table) {
            $table->date('certificate_date')->after('admin_note')->nullable();
            $table->dropColumn('signatory_name');
        });
    }

    public function down(): void
    {
        Schema::table('legitimacy_requests', function (Blueprint $table) {
            $table->dropColumn('certificate_date');
            $table->string('signatory_name')->nullable()->after('status');
        });
    }
};
