<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Framework\Bot;
use TGbotPHP\Rate\RateLimiter;
use TGbotPHP\Session\SessionManager;
use TGbotPHP\Testing\BotTester;

/**
 * Checks the PHP examples of the README, docs/ and wiki/ against the code
 *
 * Every block must parse; methods called on $bot, $api, $tester... and static
 * methods of the library must exist, with the named arguments they declare;
 * links between wiki pages must point to existing pages and headings.
 */
final class DocumentationTest extends TestCase
{
    /** Variables the examples use consistently for the same class */
    private const array VARIABLES = [
        'bot' => Bot::class,
        'api' => ApiClient::class,
        'tester' => BotTester::class,
        'limiter' => RateLimiter::class,
        'sessions' => SessionManager::class,
    ];

    /** @var array<string, class-string>|null */
    private static ?array $classes = null;

    /**
     * @return array<string, array{string}>
     */
    public static function documents(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [
            ...self::glob("$root/*.md"),
            ...self::glob("$root/docs/*.md"),
            ...self::glob("$root/wiki/*.md"),
        ];

        $documents = [];
        foreach ($files as $file) {
            $documents[substr($file, strlen($root) + 1)] = [$file];
        }

        return $documents;
    }

    #[DataProvider('documents')]
    public function testPhpExamplesMatchTheCode(string $file): void
    {
        $problems = [];

        foreach (self::phpBlocks($file) as $line => $code) {
            foreach (self::check($code) as $problem) {
                $problems[] = "line $line: $problem";
            }
        }

        self::assertSame([], $problems, basename($file));
    }

    /**
     * Links outside the wiki are checked by lychee (lint workflow)
     *
     * @return array<string, array{string}>
     */
    public static function wikiPages(): array
    {
        return array_filter(self::documents(), static fn(string $name): bool => str_starts_with($name, 'wiki/'), ARRAY_FILTER_USE_KEY);
    }

    #[DataProvider('wikiPages')]
    public function testWikiLinksPointToExistingPages(string $file): void
    {
        $problems = [];
        $contents = self::read($file);

        preg_match_all('/\]\(([^)\s]+)\)/', $contents, $links);

        foreach ($links[1] as $link) {
            if (preg_match('#^[a-z]+:|^/#i', $link) === 1) {
                continue; // external
            }

            [$page, $anchor] = explode('#', $link, 2) + [1 => null];
            $target = $page === '' ? $file : dirname($file) . "/$page.md";

            if (!is_file($target)) {
                $problems[] = "missing page: $link";
            } elseif ($anchor !== null && !in_array($anchor, self::anchors($target), true)) {
                $problems[] = "missing heading: $link";
            }
        }

        self::assertSame([], $problems, basename($file));
    }

    /**
     * @return list<string> Problems found in one block
     */
    private static function check(string $code): array
    {
        $source = str_starts_with(ltrim($code), '<?php') ? $code : "<?php\n$code";

        try {
            $tokens = \PhpToken::tokenize($source, TOKEN_PARSE);
        } catch (\ParseError $e) {
            return ['does not parse: ' . $e->getMessage()];
        }

        $tokens = array_values(array_filter($tokens, static fn(\PhpToken $t): bool => !$t->isIgnorable()));
        $classes = self::libraryClasses();
        $problems = [];

        foreach ($tokens as $i => $token) {
            $class = match (true) {
                // $bot->method(
                $token->is(T_VARIABLE) && isset(self::VARIABLES[substr($token->text, 1)])
                    && ($tokens[$i + 1] ?? null)?->is(T_OBJECT_OPERATOR) === true => self::VARIABLES[substr($token->text, 1)],
                // Formatter::method(
                $token->is(T_STRING) && isset($classes[$token->text])
                    && ($tokens[$i + 1] ?? null)?->is(T_DOUBLE_COLON) === true => $classes[$token->text],
                default => null,
            };

            $name = $tokens[$i + 2] ?? null;

            if ($class === null || $name === null || !$name->is(T_STRING) || ($tokens[$i + 3] ?? null)?->text !== '(') {
                continue;
            }

            $problems = [...$problems, ...self::checkCall($class, $name->text, $tokens, $i + 3)];
        }

        // new Config(token: ...)
        foreach ($tokens as $i => $token) {
            $class = isset($tokens[$i + 1]) ? $classes[$tokens[$i + 1]->text] ?? null : null;

            if ($token->is(T_NEW) && $class !== null && ($tokens[$i + 2] ?? null)?->text === '(') {
                $problems = [...$problems, ...self::checkCall($class, '__construct', $tokens, $i + 2)];
            }
        }

        return $problems;
    }

    /**
     * @param class-string $class
     * @param list<\PhpToken> $tokens
     * @return list<string>
     */
    private static function checkCall(string $class, string $method, array $tokens, int $open): array
    {
        $short = new ReflectionClass($class)->getShortName();

        if (!method_exists($class, $method)) {
            return $method === '__construct' ? [] : ["$short::$method() does not exist"];
        }

        $reflection = new ReflectionMethod($class, $method);

        if ($method !== '__construct' && $reflection->getName() !== $method) {
            return ["$short::$method() is spelled {$reflection->getName()}()"];
        }

        $parameters = array_map(static fn(\ReflectionParameter $p): string => $p->getName(), $reflection->getParameters());
        $problems = [];

        foreach (self::namedArguments($tokens, $open) as $argument) {
            if (!in_array($argument, $parameters, true)) {
                $problems[] = "$short::$method() has no parameter \$$argument";
            }
        }

        return $problems;
    }

    /**
     * Names of the named arguments of the call starting at the "(" token
     *
     * @param list<\PhpToken> $tokens
     * @return list<string>
     */
    private static function namedArguments(array $tokens, int $open): array
    {
        $names = [];
        $depth = 0;

        for ($i = $open; $i < count($tokens); $i++) {
            $text = $tokens[$i]->text;

            if (in_array($text, ['(', '[', '{'], true) || $tokens[$i]->is(T_CURLY_OPEN)) {
                $depth++;
            } elseif (in_array($text, [')', ']', '}'], true) && --$depth === 0) {
                break;
            }

            $previous = $tokens[$i - 1]->text;

            if ($depth === 1 && ($tokens[$i + 1] ?? null)?->text === ':' && ($previous === '(' || $previous === ',') && $tokens[$i]->is(T_STRING)) {
                $names[] = $text;
            }
        }

        return $names;
    }

    /**
     * @return array<int, string> PHP code blocks, by the line where they start
     */
    private static function phpBlocks(string $file): array
    {
        $blocks = [];
        $lines = explode("\n", self::read($file));
        $start = null;
        $code = [];

        foreach ($lines as $number => $line) {
            if ($start === null && trim($line) === '```php') {
                $start = $number + 1;
                $code = [];
            } elseif ($start !== null && trim($line) === '```') {
                $blocks[$start] = implode("\n", $code);
                $start = null;
            } elseif ($start !== null) {
                $code[] = $line;
            }
        }

        return $blocks;
    }

    /**
     * GitHub's heading anchors
     *
     * @return list<string>
     */
    private static function anchors(string $file): array
    {
        preg_match_all('/^#{1,6}\s+(.+)$/m', self::read($file), $headings);

        return array_map(
            static fn(string $heading): string => str_replace(' ', '-', (string) preg_replace('/[^\p{L}\p{N}\s_-]/u', '', strtolower(trim($heading)))),
            $headings[1],
        );
    }

    /**
     * @return array<string, class-string> Classes, interfaces and traits of the library by short name
     */
    private static function libraryClasses(): array
    {
        if (self::$classes === null) {
            $classes = [];
            $root = dirname(__DIR__, 2) . '/src/';
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                    $class = 'TGbotPHP\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($root), -4));

                    if (class_exists($class) || interface_exists($class) || trait_exists($class)) {
                        $classes[$file->getBasename('.php')] = $class;
                    }
                }
            }

            self::$classes = $classes;
        }

        return self::$classes;
    }

    /**
     * @return list<string>
     */
    private static function glob(string $pattern): array
    {
        $files = glob($pattern);

        return $files === false ? [] : $files;
    }

    private static function read(string $file): string
    {
        $contents = file_get_contents($file);
        self::assertIsString($contents);

        return $contents;
    }
}
