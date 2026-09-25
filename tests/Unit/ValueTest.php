<?php

declare(strict_types=1);

namespace TGbotPHP\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TGbotPHP\Support\Value;

final class ValueTest extends TestCase
{
    public function testInt(): void
    {
        self::assertSame(5, Value::int(5));
        self::assertSame(-12, Value::int('-12'));
        self::assertSame(3, Value::int(3.0));
        // A fraction is not silently dropped
        self::assertSame(0, Value::int(3.9));
        self::assertSame(7, Value::int('007'));
        self::assertSame(7, Value::int('abc', 7));
        self::assertSame(0, Value::int(['1']));
        self::assertSame(0, Value::int(INF));
        self::assertNull(Value::nullableInt('1e3'));
        self::assertNull(Value::nullableInt('99999999999999999999'));
        self::assertNull(Value::nullableInt(1e30));
    }

    public function testIntLimitsAreExact(): void
    {
        self::assertSame(PHP_INT_MAX, Value::nullableInt('9223372036854775807'));
        self::assertSame(PHP_INT_MIN, Value::nullableInt('-9223372036854775808'));

        // (int) would saturate to PHP_INT_MAX: a wrong id is worse than no id
        self::assertNull(Value::nullableInt('9223372036854775808'));
        self::assertNull(Value::nullableInt('-9223372036854775809'));
        self::assertNull(Value::nullableInt(' 5'));
    }

    public function testString(): void
    {
        self::assertSame('a', Value::string('a'));
        self::assertSame('42', Value::string(42));
        self::assertSame('x', Value::string(['array'], 'x'));
        self::assertNull(Value::nullableString(null));
        self::assertNull(Value::nullableString(new stdClass()));
    }

    public function testId(): void
    {
        self::assertSame(-100123, Value::id(-100123));
        self::assertSame(-100123, Value::id('-100123'));
        self::assertSame('@channel', Value::id('@channel'));
        self::assertNull(Value::id(''));
        self::assertNull(Value::id(1.5));
    }

    public function testPath(): void
    {
        $update = json_decode('{"message":{"chat":{"id":7},"list":[{"a":1}]}}', false, 512, JSON_THROW_ON_ERROR);

        self::assertSame(7, Value::path($update, 'message', 'chat', 'id'));
        self::assertNull(Value::path($update, 'message', 'from', 'id'));
        self::assertNull(Value::path(null, 'x'));
        self::assertSame(1, Value::path(['a' => ['b' => 1]], 'a', 'b'));
        self::assertInstanceOf(stdClass::class, Value::object(Value::path($update, 'message')));
        self::assertNull(Value::object('not an object'));
    }

    public function testMap(): void
    {
        self::assertSame(['a' => 1], Value::map(['a' => 1, 0 => 'dropped']));
        self::assertSame([], Value::map('x'));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function maps(): iterable
    {
        yield 'object' => [['a' => 1], true];
        yield 'empty' => [[], true];
        yield 'list' => [[1, 2], false];
        yield 'mixed keys' => [['a' => 1, 0 => 2], false];
        yield 'string' => ['a', false];
    }

    #[DataProvider('maps')]
    public function testIsMap(mixed $value, bool $expected): void
    {
        self::assertSame($expected, Value::isMap($value));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function listsOfMaps(): iterable
    {
        yield 'list of objects' => [[['a' => 1], []], true];
        yield 'empty list' => [[], true];
        yield 'object' => [['a' => ['b' => 1]], false];
        yield 'list of scalars' => [[1], false];
        yield 'null' => [null, false];
    }

    #[DataProvider('listsOfMaps')]
    public function testIsListOfMaps(mixed $value, bool $expected): void
    {
        self::assertSame($expected, Value::isListOfMaps($value));
    }

    public function testEnv(): void
    {
        putenv('TGBOTPHP_TEST_VAR=value');
        putenv('TGBOTPHP_EMPTY_VAR=');

        try {
            self::assertSame('value', Value::env('TGBOTPHP_TEST_VAR'));
            self::assertNull(Value::env('TGBOTPHP_EMPTY_VAR'));
            self::assertNull(Value::env('TGBOTPHP_MISSING_VAR'));
        } finally {
            putenv('TGBOTPHP_TEST_VAR');
            putenv('TGBOTPHP_EMPTY_VAR');
        }
    }
}
