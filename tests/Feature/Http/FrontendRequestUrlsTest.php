<?php

declare(strict_types=1);

namespace Modules\EventRegistrations\Tests\Feature\Http;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use FilesystemIterator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
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
 * a UUID and the query string is ignored.
 */
final class FrontendRequestUrlsTest extends ModuleTestCase
{
    protected ?string $moduleName = 'event-registrations';

    protected bool $autoEnableModule = true;

    private const string JS_ROOT = __DIR__ . '/../../../resources/js';

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
                $reached[] = $request['method'] . ' ' . $this->matchingRouteName($request['method'], $request['url']);
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

            $relative = 'resources/js' . substr($file->getPathname(), strlen($root));
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
                    $method === null => $this->methodOption($arguments),
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

        for ($i = $offset; $i < $length; $i++) {
            $char = $source[$i];

            if ($char === '\'' || $char === '"' || $char === '`') {
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

        $this->fail('Unterminated call at offset ' . $offset);
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

        $this->fail('Unterminated string at offset ' . $start);
    }

    private function methodOption(string $arguments): string
    {
        if (preg_match('/\bmethod\s*:\s*[\'"`](\w+)[\'"`]/', $arguments, $match) === 1) {
            return strtoupper($match[1]);
        }

        return 'GET';
    }

    /**
     * The URL a call requests: the leading string literal, with placeholders filled and
     * the query string dropped. Anything else cannot be checked and fails the test.
     */
    private function fixedUrl(string $arguments, string $call): string
    {
        $arguments = ltrim($arguments);

        if (preg_match('/^([\'"`])((?:\\\\.|(?!\1).)*)\1\s*(\+)?/s', $arguments, $match) !== 1) {
            $this->fail("{$call} must receive a string literal URL, got: " . Str::limit($arguments, 80));
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
