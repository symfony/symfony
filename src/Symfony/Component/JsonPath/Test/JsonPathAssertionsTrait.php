<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Test;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\JsonPath\JsonPath;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
trait JsonPathAssertionsTrait
{
    /**
     * @throws ExpectationFailedException
     */
    final public static function assertJsonPathEquals(mixed $expectedValue, JsonPath|string $jsonPath, string $json, string $message = ''): void
    {
        Assert::assertThat($expectedValue, new JsonPathEquals($jsonPath, $json), $message);
    }

    /**
     * @throws ExpectationFailedException
     */
    final public static function assertJsonPathNotEquals(mixed $expectedValue, JsonPath|string $jsonPath, string $json, string $message = ''): void
    {
        Assert::assertThat($expectedValue, new JsonPathNotEquals($jsonPath, $json), $message);
    }

    /**
     * @throws ExpectationFailedException
     */
    final public static function assertJsonPathCount(int $expectedCount, JsonPath|string $jsonPath, string $json, string $message = ''): void
    {
        Assert::assertThat($expectedCount, new JsonPathCount($jsonPath, $json), $message);
    }

    /**
     * @throws ExpectationFailedException
     */
    final public static function assertJsonPathSame(mixed $expectedValue, JsonPath|string $jsonPath, string $json, string $message = ''): void
    {
        Assert::assertThat($expectedValue, new JsonPathSame($jsonPath, $json), $message);
    }

    /**
     * @throws ExpectationFailedException
     */
    final public static function assertJsonPathNotSame(mixed $expectedValue, JsonPath|string $jsonPath, string $json, string $message = ''): void
    {
        Assert::assertThat($expectedValue, new JsonPathNotSame($jsonPath, $json), $message);
    }

    /**
     * @throws ExpectationFailedException
     */
    final public static function assertJsonPathContains(mixed $expectedValue, JsonPath|string $jsonPath, string $json, bool $strict = true, string $message = ''): void
    {
        Assert::assertThat($expectedValue, new JsonPathContains($jsonPath, $json, $strict), $message);
    }

    /**
     * @throws ExpectationFailedException
     */
    final public static function assertJsonPathNotContains(mixed $expectedValue, JsonPath|string $jsonPath, string $json, bool $strict = true, string $message = ''): void
    {
        Assert::assertThat($expectedValue, new JsonPathNotContains($jsonPath, $json, $strict), $message);
    }
}
