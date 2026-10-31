<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Http\Controllers;

use App\Application\Surveys\Contracts\SurveySubjectLinksInterface;
use App\Application\Surveys\DTOs\SurveyLinkDTO;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\EventRegistrations\Infrastructure\Surveys\EventSurveySubjectSource;
use Modules\EventRegistrations\Infrastructure\Surveys\SurveyableEvents;

/**
 * The event's surveys for the person viewing its page, through the core's contract: whoever
 * manages them gets their page, and each registrant the surveys sent to them. Without a surveys
 * module the core answers with no links, and null means nothing to show.
 */
final class EventSurveyLinksController extends Controller
{
    public function __construct(
        private readonly SurveyableEvents $events,
        private readonly SurveySubjectLinksInterface $surveyLinks,
    ) {}

    public function __invoke(Request $request, string $eventId): JsonResponse
    {
        $user = $request->user();
        $event = $this->events->find($eventId);

        // The route requires auth: the first check only narrows the type
        if ($user === null || ! $event instanceof EventModel) {
            return response()->json(['data' => null]);
        }

        $links = $this->surveyLinks->forSubject(EventSurveySubjectSource::TYPE, $event->id, (string) $user->getAuthIdentifier());

        return response()->json(['data' => $links->isEmpty() ? null : [
            'manageUrl' => $links->manageUrl,
            'surveys' => array_map(static fn (SurveyLinkDTO $link): array => [
                'title' => $link->title,
                'url' => $link->url,
                'action' => $link->action->value,
            ], $links->surveys),
        ]]);
    }
}
