<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_blotters', function (Blueprint $table) {
            $table->id();
            
            // Reporter Information
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('email');
            $table->integer('age');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->string('address');
            $table->string('contact_number');
            
            // Incident Information
            $table->string('incident_type');
            $table->date('incident_date');
            $table->time('incident_time');
            $table->string('incident_location');
            $table->longText('narrative');
            $table->string('complaint_against');
            
            // Witness Information
            $table->string('witness1_name')->nullable();
            $table->string('witness1_contact')->nullable();
            $table->string('witness2_name')->nullable();
            $table->string('witness2_contact')->nullable();
            
            // Status
            $table->enum('status', ['filed', 'under_investigation', 'resolved', 'closed'])->default('filed');
            
            $table->timestamps();
            
            // Indexes
            $table->index('incident_date');
            $table->index('incident_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangay_blotters');
    }
};
