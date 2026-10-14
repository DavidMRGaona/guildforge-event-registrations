<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Application\DTOs;

/**
 * What a user sees of the event they registered for: enough to recognise it and open its page.
 */
final readonly class RegisteredEventDTO
{
    public function __construct(
        public string $id,
        public string $title,
        public string $slug,
        public string $startDate,
        public string $endDate,
        public ?string $location,
        public string $url,
    ) {}

    /**
     * @return array{id: string, title: string, slug: string, start_date: string, end_date: string, location: string|null, url: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'location' => $this->location,
            'url' => $this->url,
        ];
    }
}
