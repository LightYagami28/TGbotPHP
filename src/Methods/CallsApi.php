<?php

declare(strict_types=1);

namespace TGbotPHP\Methods;

/**
 * Typed API calls the method traits rely on, implemented by HttpClientTrait.
 *
 * Values in $options are merged over $params, so any optional or newer
 * parameter can be passed without a dedicated argument.
 */
trait CallsApi
{
    /**
     * Call a method returning a JSON object.
     *
     * @param array<string, mixed> $params Named API parameters
     * @param array<string, mixed> $options Extra parameters overriding $params
     * @return array<string, mixed> Decoded object
     */
    abstract protected function apiCallObject(string $method, array $params = [], array $options = []): array;

    /**
     * Call a method returning a JSON array of objects.
     *
     * @param array<string, mixed> $params Named API parameters
     * @param array<string, mixed> $options Extra parameters overriding $params
     * @return list<array<string, mixed>> Decoded list
     */
    abstract protected function apiCallList(string $method, array $params = [], array $options = []): array;

    /**
     * Call a method returning updates decoded as webhook objects.
     *
     * @param array<string, mixed> $params Named API parameters
     * @return list<\stdClass> Decoded updates
     */
    abstract protected function apiCallObjects(string $method, array $params = []): array;

    /**
     * Call a method returning an object or true.
     *
     * @param array<string, mixed> $params Named API parameters
     * @param array<string, mixed> $options Extra parameters overriding $params
     * @return array<string, mixed>|bool Decoded object or true
     */
    abstract protected function apiCallObjectOrTrue(
        string $method,
        array $params = [],
        array $options = [],
    ): array|bool;

    /**
     * Call a method returning a boolean.
     *
     * @param array<string, mixed> $params Named API parameters
     * @param array<string, mixed> $options Extra parameters overriding $params
     */
    abstract protected function apiCallBool(string $method, array $params = [], array $options = []): bool;

    /**
     * Call a method returning an integer.
     *
     * @param array<string, mixed> $params Named API parameters
     * @param array<string, mixed> $options Extra parameters overriding $params
     */
    abstract protected function apiCallInt(string $method, array $params = [], array $options = []): int;

    /**
     * Call a method returning a string.
     *
     * @param array<string, mixed> $params Named API parameters
     * @param array<string, mixed> $options Extra parameters overriding $params
     */
    abstract protected function apiCallString(string $method, array $params = [], array $options = []): string;
}
