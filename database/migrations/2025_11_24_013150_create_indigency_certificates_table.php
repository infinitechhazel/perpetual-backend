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
        Schema::create('indigency_certificates', function (Blueprint $table) {
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
            
            // Indigency Specific Information
            $table->string('monthly_income')->nullable();
            $table->integer('number_of_dependents')->default(0);
            $table->text('purpose'); // e.g., Medical Assistance, Burial Assistance, Educational Assistance
            $table->text('reason_for_indigency')->nullable(); // Additional context
            
            // Documents
            $table->string('valid_id_path')->nullable();
            $table->string('supporting_document_path')->nullable(); // Optional: medical records, bills, etc.
            
            // Status and Processing
            $table->enum('status', ['pending', 'approved', 'rejected', 'released'])->default('pending');
            $table->text('admin_remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('reference_number');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indigency_certificates');
    }
};