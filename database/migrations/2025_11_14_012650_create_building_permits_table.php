<?php

// database/migrations/xxxx_xx_xx_create_building_permits_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('building_permits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reference_number')->unique();
            
            // Project Details
            $table->string('project_type'); // new-construction, renovation, addition, repair
            $table->string('project_scope'); // residential, commercial, industrial
            $table->text('project_description');
            $table->decimal('lot_area', 10, 2)->nullable();
            $table->decimal('floor_area', 10, 2)->nullable();
            $table->integer('number_of_floors');
            $table->decimal('estimated_cost', 15, 2);
            
            // Owner Information
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_phone');
            $table->text('owner_address');
            $table->text('property_address');
            $table->string('barangay');
            
            // Documents (stored in public directory)
            $table->string('building_plans_path')->nullable();
            $table->string('land_title_path')->nullable();
            
            // Status
            $table->enum('status', ['pending', 'processing', 'approved', 'rejected', 'completed'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('user_id');
            $table->index('reference_number');
            $table->index('status');
            $table->index('barangay');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('building_permits');
    }
};