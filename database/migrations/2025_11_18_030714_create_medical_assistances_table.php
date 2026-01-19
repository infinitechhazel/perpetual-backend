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
        Schema::create('medical_assistances', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            
            // Personal Information
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->text('address');
            $table->date('birth_date');
            $table->integer('age');
            $table->enum('sex', ['male', 'female']);
            
            // Medical Information
            $table->text('diagnosis');
            $table->string('hospital_name');
            $table->string('doctor_name');
            $table->decimal('estimated_cost', 12, 2);
            
            // Financial Information
            $table->decimal('monthly_income', 12, 2);
            $table->decimal('assistance_amount_requested', 12, 2);
            
            // Documents
            $table->string('supporting_documents')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_assistances');
    }
};