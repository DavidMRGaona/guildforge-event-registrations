<?php

declare(strict_types=1);

use App\Application\Services\TimezoneServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Events whose end was announced to the surveys module (once per event)
        Schema::create('event_registrations_survey_ends', function (Blueprint $table): void {
            $table->uuid('event_id')->primary();
            $table->dateTime('announced_at');

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->cascadeOnDelete();
        });

        // Events that ended before this version are history: never announce them
        $now = app(TimezoneServiceInterface::class)->toStorage(CarbonImmutable::now());

        DB::table('events')->where('end_date', '<=', $now)->orderBy('id')->select('id')->chunk(500, function ($events) use ($now): void {
            DB::table('event_registrations_survey_ends')->insert($events->map(static fn (object $event): array => [
                'event_id' => $event->id,
                'announced_at' => $now->format('Y-m-d H:i:s'),
            ])->all());
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations_survey_ends');
    }
};
