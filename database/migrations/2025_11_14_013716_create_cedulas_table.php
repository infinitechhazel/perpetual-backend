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
        Schema::create('cedulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 20);
            $table->string('address', 500);
            $table->date('birth_date');
            $table->enum('civil_status', ['single', 'married', 'widowed', 'separated']);
            $table->string('citizenship', 100);
            $table->string('occupation');
            $table->string('tin_number', 50)->nullable();
            $table->decimal('height', 5, 2);
            $table->decimal('weight', 5, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            // Indexes for better query performance
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cedulas');
    }
};