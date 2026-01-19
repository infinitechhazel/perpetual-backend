<?php

// database/migrations/2024_01_01_000001_create_reports_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('category', [
                'road',
                'streetlight',
                'garbage',
                'drainage',
                'traffic',
                'vandalism',
                'noise',
                'other'
            ]);
            $table->string('title');
            $table->text('description');
            $table->string('location');
            $table->enum('urgency', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', [
                'pending',
                'under_review',
                'in_progress',
                'resolved',
                'rejected'
            ])->default('pending');
            $table->timestamp('timestamp');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('category');
        });

        Schema::create('report_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('path');
            $table->string('type'); // image or video
            $table->integer('size'); // in bytes
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_files');
        Schema::dropIfExists('reports');
    }
};