<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vlogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->string('category');
            $table->text('description')->nullable();
            $table->longText('content');
            $table->boolean('is_active')->default(true);
            $table->string('video')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vlogs');
    }
};
