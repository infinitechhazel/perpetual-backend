<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('office_contact', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('office_location'); // e.g. "Las Piñas City Hall"
            $table->string('phone_number')->nullable(); // optional
            $table->string('email')->nullable(); // optional
            $table->string('office_hours')->nullable(); // e.g. "Mon-Fri 8AM-5PM"
            $table->string('map_link')->nullable(); // Google Maps or other link
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
