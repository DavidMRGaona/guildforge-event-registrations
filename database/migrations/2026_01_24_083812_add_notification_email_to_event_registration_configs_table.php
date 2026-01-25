<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_registrations_configs', function (Blueprint $table): void {
            $table->string('notification_email')->nullable()->after('confirmation_message');
        });
    }

    public function down(): void
    {
        Schema::table('event_registrations_configs', function (Blueprint $table): void {
            $table->dropColumn('notification_email');
        });
    }
};
