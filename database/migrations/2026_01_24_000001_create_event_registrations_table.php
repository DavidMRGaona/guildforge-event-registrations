<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('event_id');
            $table->uuid('user_id');
            $table->string('state')->default('pending');
            $table->integer('position')->nullable();
            $table->json('form_data')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Each user can only register once per event
            $table->unique(['event_id', 'user_id']);

            // Index for common queries
            $table->index(['event_id', 'state']);
            $table->index(['user_id']);
            $table->index(['event_id', 'position']);

            // Foreign keys
            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations_registrations');
    }
};
