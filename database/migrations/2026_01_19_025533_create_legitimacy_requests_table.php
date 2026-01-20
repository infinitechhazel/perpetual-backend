<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legitimacy_requests', function (Blueprint $table) {
            $table->id();

            // Foreign key to users table
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // User-submitted fields
            $table->string('alias');
            $table->string('chapter');
            $table->string('position');
            $table->string('fraternity_number');

            // Admin-only fields
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('signatory_name')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legitimacy_requests');
    }
};
