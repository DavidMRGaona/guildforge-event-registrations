<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use FilesystemIterator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Every URL the module's Vue and TypeScript files request (fetch, axios, Inertia router,
 * window.location and static href attributes) must reach a registered route with that
 * method. There are no component tests in this module, so a request to a URL that does
 * not exist (such as an /api/ path) would only show up as a 404 in the browser.
 *
 * URLs must be string literals so they can be checked: `${…}` placeholders are filled with
 * a UUID and the query string is ignored. The method too: when a call takes options, they
 * must be an object literal whose `method` (if any) is a string literal, or the test fails.
 */
final class FrontendRequestUrlsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private const string JS_ROOT = __DIR__.'/../../../resources/js';

    /**
     * Call patterns and the HTTP method they use; null means "read `method:` from the options".
     *
     * @var array<string, string|null>
     */
    private const array CALLS = [
        '/\bfetch\s*\(/' => null,
        '/\baxios\s*\(/' => null,
        '/\baxios\.(get|post|put|patch|delete)\s*\(/' => '$1',
        '/\brouter\.visit\s*\(/' => null,
        '/\brouter\.(get|post|put|patch|delete)\s*\(/' => '$1',
        '/\bwindow\.location\.(?:assign|replace)\s*\(/' => 'GET',
        '/\bwindow\.location(?:\.href)?\s*=(?!=)/' => 'GET',
    ];

    public function test_every_request_of_the_frontend_reaches_a_registered_route(): void
    {
        $requests = $this->frontendRequests();
        $failures = [];

        foreach ($requests as $request) {
            $routeName = $this->matchingRouteName($request['method'], $request['url']);

            if ($routeName === false) {
                $failures[] = "{$request['file']}: {$request['method']} {$request['url']}";
            }
        }

        $this->assertSame([], $failures, 'These frontend requests do not reach any registered route');
    }

    public function test_the_registration_button_requests_are_found(): void
    {
        $reached = [];

        foreach ($this->frontendRequests() as $request) {
            if (str_ends_with($request['file'], 'components/RegistrationButton.vue')) {
                $reached[] = $request['method'].' '.$this->matchingRouteName($request['method'], $request['url']);
            }
        }

        foreach ([
            'GET event-registrations.status',
            'GET event-registrations.show',
            'POST event-registrations.store',
            'DELETE event-registrations.destroy',
            'GET login',
        ] as $expected) {
            $this->assertContains($expected, $reached);
        }
    }

    public function test_the_profile_section_request_is_found(): void
    {
        $reached = [];

        foreach ($this->frontendRequests() as $request) {
            if (str_ends_with($request['file'], 'components/UserRegistrations.vue')) {
                $reached[] = $request['method'].' '.$this->matchingRouteName($request['method'], $request['url']);
            }
        }

        $this->assertContains('GET event-registrations.my-registrations', $reached);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function readableMethodProvider(): array
    {
        return [
            'no options' => ["fetch('/eventos')", 'GET'],
            'options without a method' => ["fetch('/eventos', { headers: { Accept: 'application/json' } })", 'GET'],
            'literal method' => ["fetch('/eventos', { method: 'DELETE' })", 'DELETE'],
            'nested method in the body is not the request method' => [
                "fetch('/eventos', { method: 'POST', body: JSON.stringify({ method: 'GET' }) })",
                'POST',
            ],
            'router visit' => ["router.visit('/eventos', { method: 'patch' })", 'PATCH'],
        ];
    }

    #[DataProvider('readableMethodProvider')]
    public function test_the_method_is_read_from_the_top_level_options(string $source, string $method): void
    {
        $this->assertSame([['method' => $method, 'url' => '/eventos']], $this->requestsIn($source));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unreadableMethodProvider(): array
    {
        return [
            'ternary' => ["fetch('/eventos', { method: isRegistered ? 'DELETE' : 'POST' })"],
            'variable' => ["fetch('/eventos', { method: verb })"],
            'shorthand' => ["fetch('/eventos', { method })"],
            'spread' => ["fetch('/eventos', { ...options })"],
            'options variable' => ["fetch('/eventos', options)"],
        ];
    }

    #[DataProvider('unreadableMethodProvider')]
    public function test_a_method_that_cannot_be_read_fails_the_check(string $source): void
    {
        try {
            $this->requestsIn($source);
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString('method', $e->getMessage());

            return;
        }

        $this->fail("A method that cannot be read was taken as GET: {$source}");
    }

    public function test_no_frontend_file_calls_an_api_prefixed_url(): void
    {
        foreach ($this->frontendRequests() as $request) {
            $this->assertStringStartsNotWith('/api/', $request['url'], "{$request['file']}: {$request['url']}");
        }
    }

    public function test_the_old_api_url_for_the_users_registrations_does_not_exist(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->getJson('/api/my-registrations')
            ->assertNotFound();
    }

    /**
     * @return list<array{file: string, method: string, url: string}>
     */
    private function frontendRequests(): array
    {
        $root = realpath(self::JS_ROOT);
        $this->assertIsString($root);

        $requests = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! in_array($file->getExtension(), ['vue', 'ts'], true)) {
                continue;
            }

            $relative = 'resources/js'.substr($file->getPathname(), strlen($root));
            $source = file_get_contents($file->getPathname());
            $this->assertIsString($source);

            foreach ($this->requestsIn($source) as $request) {
                $requests[] = ['file' => $relative, ...$request];
            }
        }

        return $requests;
    }

    /**
     * @return list<array{method: string, url: string}>
     */
    private function requestsIn(string $source): array
    {
        $requests = [];

        foreach (self::CALLS as $pattern => $method) {
            preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $isAssignment = str_ends_with($match[0][0], '=');
                $arguments = $this->argumentsAfter($source, $match[0][1] + strlen($match[0][0]), $isAssignment);
                $resolvedMethod = match (true) {
                    $method === '$1' => strtoupper($match[1][0] ?? $this->fail("{$pattern} captures no method")),
                    $method === null => $this->methodOption($arguments, $match[0][0]),
                    default => $method,
                };

                $requests[] = ['method' => $resolvedMethod, 'url' => $this->fixedUrl($arguments, $match[0][0])];
            }
        }

        preg_match_all('/(?<![:\w-])href="(\/[^"]*)"/', $source, $hrefs);

        foreach ($hrefs[1] as $href) {
            $requests[] = ['method' => 'GET', 'url' => $this->withoutQuery($href)];
        }

        return $requests;
    }

    /**
     * The text of a call's arguments (up to its closing parenthesis) or of an assignment's
     * right-hand side (up to `;` or the end of the line), skipping strings and nested brackets.
     */
    private function argumentsAfter(string $source, int $offset, bool $isAssignment): string
    {
        $depth = 0;
        $length = strlen($source);

        // An assigned value may start on the next line (Prettier wraps long assignments)
        if ($isAssignment) {
            $offset += strspn($source, " \t\r\n", $offset);
        }

        for ($i = $offset; $i < $length; $i++) {
            $char = $source[$i];

            if (in_array($char, ['\'', '"', '`'], true)) {
                $i = $this->endOfString($source, $i);

                continue;
            }

            if (in_array($char, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($char, [')', ']', '}'], true)) {
                if ($depth === 0) {
                    return substr($source, $offset, $i - $offset);
                }
                $depth--;
            } elseif ($isAssignment && $depth === 0 && ($char === ';' || $char === "\n")) {
                return substr($source, $offset, $i - $offset);
            }
        }

        $this->fail('Unterminated call at offset '.$offset);
    }

    private function endOfString(string $source, int $start): int
    {
        $quote = $source[$start];
        $length = strlen($source);

        for ($i = $start + 1; $i < $length; $i++) {
            if ($source[$i] === '\\') {
                $i++;
            } elseif ($source[$i] === $quote) {
                return $i;
            }
        }

        $this->fail('Unterminated string at offset '.$start);
    }

    /**
     * The method set in a call's options (its second argument): GET without options or
     * without `method`. Options that hide the method (a variable, a spread, a `method` that is
     * not a string literal) fail the test instead of being taken as GET.
     */
    private function methodOption(string $arguments, string $call): string
    {
        $options = trim($this->topLevelParts($arguments)[1] ?? '');

        if ($options === '') {
            return 'GET';
        }

        if (! str_starts_with($options, '{') || ! str_ends_with($options, '}')) {
            $this->fail("{$call} must receive its options as an object literal to read the method, got: ".Str::limit($options, 80));
        }

        $method = 'GET';

        foreach ($this->topLevelParts(substr($options, 1, -1)) as $property) {
            $property = trim($property);

            if (str_starts_with($property, '...')) {
                $this->fail("{$call} spreads its options, so the method cannot be read: ".Str::limit($property, 80));
            }

            if (preg_match('/^[\'"]?method[\'"]?\b/', $property) !== 1) {
                continue;
            }

            if (preg_match('/^[\'"]?method[\'"]?\s*:\s*([\'"`])(\w+)\1$/', $property, $match) !== 1) {
                $this->fail("{$call} must set its method as a string literal, got: ".Str::limit($property, 80));
            }

            $method = strtoupper($match[2]);
        }

        return $method;
    }

    /**
     * Splits a list (call arguments or object properties) at its top-level commas, skipping
     * strings and nested brackets.
     *
     * @return list<string>
     */
    private function topLevelParts(string $text): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if (in_array($char, ['\'', '"', '`'], true)) {
                $i = $this->endOfString($text, $i);
            } elseif (in_array($char, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($char, [')', ']', '}'], true)) {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = substr($text, $start, $i - $start);
                $start = $i + 1;
            }
        }

        $parts[] = substr($text, $start);

        return array_values(array_filter($parts, fn (string $part): bool => trim($part) !== ''));
    }

    /**
     * The URL a call requests: the leading string literal, with placeholders filled and
     * the query string dropped. Anything else cannot be checked and fails the test.
     */
    private function fixedUrl(string $arguments, string $call): string
    {
        $arguments = ltrim($arguments);

        if (preg_match('/^([\'"`])((?:\\\\.|(?!\1).)*)\1\s*(\+)?/s', $arguments, $match) !== 1) {
            $this->fail("{$call} must receive a string literal URL, got: ".Str::limit($arguments, 80));
        }

        $url = $match[2];
        $isConcatenated = ($match[3] ?? '') === '+';

        if ($isConcatenated && ! str_contains($url, '?')) {
            $this->fail("{$call} builds its path by concatenation, use a template literal: {$url}");
        }

        $url = preg_replace('/\$\{[^}]*\}/', (string) Str::uuid(), $url);
        $this->assertIsString($url);

        return $this->withoutQuery($url);
    }

    private function withoutQuery(string $url): string
    {
        return (string) preg_replace('/[?#].*$/s', '', $url);
    }

    private function matchingRouteName(string $method, string $url): string|false
    {
        try {
            return (string) Route::getRoutes()->match(Request::create($url, $method))->getName();
        } catch (HttpException) {
            return false;
        }
    }
}
