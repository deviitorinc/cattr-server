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
        Schema::create('user_employee_info', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id'); // Foreign key to users table - matching increments() type
            $table->string('employee_id')->nullable(); // Employee ID field
            $table->date('joined_date')->nullable(); // Joined Date field
            $table->timestamps();
            
            // Add foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            // Add index for better query performance
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_employee_info');
    }
};
