<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cattr_task_start_webhook_attempts', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('interval_id')->unique();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('task_id');
            $table->date('tracking_date');
            $table->string('person_name', 120);
            $table->string('task_name', 255);
            $table->string('status', 16);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'tracking_date', 'interval_id'], 'task_start_user_date_interval_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cattr_task_start_webhook_attempts');
    }
};
