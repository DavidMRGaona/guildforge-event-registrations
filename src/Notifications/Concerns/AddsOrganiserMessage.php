<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Notifications\Concerns;

use App\Notifications\Support\EscapesMarkdown;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The organiser's own message of the confirmed-spot emails: printed as plain text, never as Markdown.
 */
trait AddsOrganiserMessage
{
    private function addOrganiserMessage(MailMessage $message, ?string $organiserMessage): MailMessage
    {
        // One line: a line break could start a block (list, heading, quote) of its own
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $organiserMessage));

        if ($text === '') {
            return $message;
        }

        return $message->line('**'.__('event-registrations::messages.emails.organiser_message').':** '.EscapesMarkdown::escape($text));
    }
}
