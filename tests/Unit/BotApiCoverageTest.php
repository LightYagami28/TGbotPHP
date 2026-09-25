<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use TGbotPHP\Core\ApiClient;
use TGbotPHP\Core\Config;
use TGbotPHP\Core\UpdateParser;
use TGbotPHP\Support\Value;
use TGbotPHP\Tests\Support\FakeTransport;
use TGbotPHP\Tests\Support\Updates;
use TGbotPHP\Types\InputFile;

/**
 * Checks the update types and every Bot API method against tools/bot-api.json,
 * extracted from the official documentation by tools/bot-api-spec.php
 *
 * Each method is called with a value for every argument; the request must use
 * the right method name, send every required parameter and nothing unknown,
 * and the result must be accepted.
 */
final class BotApiCoverageTest extends TestCase
{
    /** Results returned by the fake transport, per kind of result */
    private const array RESULTS = [
        'bool' => true,
        'int' => 5,
        'string' => 'value',
        'list' => [['id' => 1]],
        'object' => ['id' => 1],
        'object|true' => true,
    ];

    /**
     * @return array{version: string, updates: list<string>, methods: array<string, array{returns: string, parameters: array<string, array{type: string, required: bool}>}>}
     */
    private static function spec(): array
    {
        $spec = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/tools/bot-api.json'), true, flags: JSON_THROW_ON_ERROR);
        $spec = Value::map($spec);

        $methods = [];
        foreach (Value::map($spec['methods'] ?? null) as $name => $method) {
            $method = Value::map($method);
            $parameters = [];

            foreach (Value::map($method['parameters'] ?? null) as $parameter => $definition) {
                $definition = Value::map($definition);
                $parameters[$parameter] = [
                    'type' => Value::string($definition['type'] ?? null),
                    'required' => ($definition['required'] ?? false) === true,
                ];
            }

            $methods[$name] = ['returns' => Value::string($method['returns'] ?? null), 'parameters' => $parameters];
        }

        return [
            'version' => Value::string($spec['version'] ?? null),
            'updates' => array_map(strval(...), array_keys(Value::map($spec['updates'] ?? null))),
            'methods' => $methods,
        ];
    }

    /**
     * @return array<string, array{string, string, array<string, array{type: string, required: bool}>}>
     */
    public static function methods(): array
    {
        $cases = [];

        foreach (self::spec()['methods'] as $name => $method) {
            $cases[$name] = [$name, $method['returns'], $method['parameters']];
        }

        return $cases;
    }

    public function testSpecIsCurrent(): void
    {
        self::assertMatchesRegularExpression('/^\d+\.\d+$/', self::spec()['version']);
        self::assertSame(self::spec()['version'], ApiClient::BOT_API_VERSION);
    }

    public function testUpdateTypesMatchTheDocumentation(): void
    {
        self::assertSame(self::spec()['updates'], UpdateParser::UPDATE_TYPES);
    }

    /**
     * @param array<string, array{type: string, required: bool}> $parameters
     */
    #[DataProvider('methods')]
    public function testMethodMatchesTheDocumentation(string $name, string $returns, array $parameters): void
    {
        self::assertTrue(method_exists(ApiClient::class, $name), "ApiClient::$name() is missing");

        $transport = new FakeTransport();
        $transport->queueResult(self::RESULTS[$returns]);
        $client = new ApiClient(new Config(Updates::TOKEN), $transport);

        $method = new ReflectionMethod(ApiClient::class, $name);
        $method->invokeArgs($client, array_map(self::argument(...), $method->getParameters()));

        $request = $transport->lastRequest();
        self::assertSame($name, $request['method']);

        $sent = array_keys($request['fields']);
        $unknown = array_diff($sent, array_keys($parameters));
        $missing = array_diff(array_keys(array_filter($parameters, static fn(array $p): bool => $p['required'])), $sent);

        self::assertSame([], array_values($unknown), "$name sends unknown parameters");
        self::assertSame([], array_values($missing), "$name does not send required parameters");
    }

    /**
     * A value for every argument, so that optional parameters are sent too
     */
    private static function argument(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        $names = $type instanceof ReflectionNamedType ? [$type->getName()] : array_map(
            static fn(\ReflectionType $t): string => $t instanceof ReflectionNamedType ? $t->getName() : 'mixed',
            $type instanceof \ReflectionUnionType ? $type->getTypes() : [],
        );

        return match (true) {
            $parameter->getName() === 'options' => [],
            in_array(InputFile::class, $names, true) => InputFile::fromContents('data', 'file.bin'),
            in_array('int', $names, true) => 1,
            in_array('string', $names, true) => $parameter->getName() === 'url' ? 'https://example.com/hook' : 'x',
            in_array('float', $names, true) => 1.5,
            in_array('bool', $names, true) => true,
            in_array('array', $names, true) => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : [['type' => 'x']],
            default => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
        };
    }
}
