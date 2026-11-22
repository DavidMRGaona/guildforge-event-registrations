<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Events whose activity was published to the activity contract's consumers, with the end
        // that was published: an event whose end changed since is published again
        Schema::create('event_registrations_activity_ends', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->dateTime('ends_at');
            $table->dateTime('announced_at');

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations_activity_ends');
    }
};
