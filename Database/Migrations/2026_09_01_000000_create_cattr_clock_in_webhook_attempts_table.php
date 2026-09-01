<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattr_clock_in_webhook_attempts', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('person_name');
            $table->date('clock_in_date');
            $table->string('status', 16);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'clock_in_date'], 'clock_in_webhook_user_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cattr_clock_in_webhook_attempts');
    }
};
