<?php
// database/migrations/2024_01_xx_create_good_moral_certificates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('good_moral_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reference_number')->unique();
            
            // Personal Information
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->text('address');
            $table->date('birth_date');
            $table->integer('age');
            $table->enum('sex', ['male', 'female']);
            $table->enum('civil_status', ['single', 'married', 'widowed', 'divorced', 'separated']);
            
            // Residency Information
            $table->string('barangay');
            $table->integer('years_of_residency');
            $table->string('occupation')->nullable();
            $table->text('purpose');
            
            // Documents
            $table->string('valid_id_path');
            $table->string('proof_of_residency_path')->nullable();
            
            // Status and Processing
            $table->enum('status', ['pending', 'approved', 'rejected', 'released'])->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('reference_number');
            $table->index('user_id');
            $table->index('status');
            $table->index('barangay');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('good_moral_certificates');
    }
};