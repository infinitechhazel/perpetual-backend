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
        Schema::create('about_section', function (Blueprint $table) {  // Changed from 'about'
            $table->id();

            // Community
            $table->string('community_header')->nullable();
            $table->string('community_title')->nullable();
            $table->text('community_content')->nullable();
            $table->text('community_list')->nullable();
            $table->string('community_card_icon')->nullable();
            $table->string('community_card_number')->nullable();
            $table->string('community_card_category')->nullable();

            // Goals
            $table->string('goals_header')->nullable();
            $table->string('goals_title')->nullable();
            $table->text('goals_description')->nullable();
            $table->text('goals_content')->nullable();
            $table->string('goals_card_icon')->nullable();
            $table->string('goals_card_title')->nullable();
            $table->text('goals_card_content')->nullable();
            $table->text('goals_card_list')->nullable();

            // Mission & Vision
            $table->string('mission_and_vision_header')->nullable();
            $table->string('mission_and_vision_title')->nullable();
            $table->string('mission_and_vision_description')->nullable();
            $table->text('mission_content')->nullable();
            $table->text('vision_content')->nullable();

            // Objectives
            $table->string('objectives_header')->nullable();
            $table->string('objectives_title')->nullable();
            $table->text('objectives_description')->nullable();
            $table->string('objectives_card_title')->nullable();
            $table->text('objectives_card_content')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('about_section');  // Changed from 'about_sections'
    }
};