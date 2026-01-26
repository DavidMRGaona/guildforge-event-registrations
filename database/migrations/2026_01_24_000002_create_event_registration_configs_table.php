<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations_configs', function (Blueprint $table): void {
            // event_id is the primary key (one config per event)
            $table->uuid('event_id')->primary();
            $table->boolean('registration_enabled')->default(true);
            $table->integer('max_participants')->nullable();
            $table->boolean('waiting_list_enabled')->default(true);
            $table->integer('max_waiting_list')->nullable();
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->timestamp('cancellation_deadline')->nullable();
            $table->boolean('requires_confirmation')->default(false);
            $table->boolean('requires_payment')->default(false);
            $table->boolean('members_only')->default(false);
            $table->json('custom_fields')->nullable();
            $table->text('confirmation_message')->nullable();
            $table->string('notification_email')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations_configs');
    }
};
