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
        Schema::create('business_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Business Information
            $table->string('business_name');
            $table->string('business_type');
            $table->string('business_category');
            $table->string('business_category_other')->nullable();
            $table->text('business_description');
            
            // Owner Information
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_phone');
            $table->text('owner_address');
            
            // Location Details
            $table->text('business_address');
            $table->string('barangay');
            $table->string('lot_number')->nullable();
            $table->string('floor_area');
            
            // Application Status
            $table->enum('status', ['pending', 'processing', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->string('permit_number')->nullable()->unique();
            $table->date('approved_at')->nullable();
            $table->date('expires_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('status');
            $table->index('permit_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_permits');
    }
};