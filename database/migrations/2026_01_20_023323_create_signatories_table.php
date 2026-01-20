<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legitimacy_request_id');
            $table->string('name');
            $table->date('signed_date')->nullable();
            $table->timestamps();

            $table->foreign('legitimacy_request_id')
                ->references('id')
                ->on('legitimacy_requests')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatories');
    }
};
