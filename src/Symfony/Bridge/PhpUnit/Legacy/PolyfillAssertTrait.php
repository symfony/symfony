<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Legacy;

use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\Constraint\StringContains;
use PHPUnit\Framework\Constraint\TraversableContains;

/**
 * This trait is @internal
 */
trait PolyfillAssertTrait
{
    /**
     * @param float  $delta
     * @param string $message
     *
     * @return void
     */
    public static function assertEqualsWithDelta($expected, $actual, $delta, $message = '')
    {
        $constraint = new IsEqual($expected, $delta);
        static::assertThat($actual, $constraint, $message);
    }

    /**
     * @param iterable $haystack
     * @param string   $message
     *
     * @return void
     */
    public static function assertContainsEquals($needle, $haystack, $message = '')
    {
        $constraint = new TraversableContains($needle, false, false);
        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param iterable $haystack
     * @param string   $message
     *
     * @return void
     */
    public static function assertNotContainsEquals($needle, $haystack, $message = '')
    {
        $constraint = new LogicalNot(new TraversableContains($needle, false, false));
        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsArray($actual, $message = '')
    {
        static::assertInternalType('array', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsBool($actual, $message = '')
    {
        static::assertInternalType('bool', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsFloat($actual, $message = '')
    {
        static::assertInternalType('float', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsInt($actual, $message = '')
    {
        static::assertInternalType('int', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsNumeric($actual, $message = '')
    {
        static::assertInternalType('numeric', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsObject($actual, $message = '')
    {
        static::assertInternalType('object', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsResource($actual, $message = '')
    {
        static::assertInternalType('resource', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsString($actual, $message = '')
    {
        static::assertInternalType('string', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsScalar($actual, $message = '')
    {
        static::assertInternalType('scalar', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsCallable($actual, $message = '')
    {
        static::assertInternalType('callable', $actual, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertIsIterable($actual, $message = '')
    {
        static::assertInternalType('iterable', $actual, $message);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     *
     * @return void
     */
    public static function assertStringContainsString($needle, $haystack, $message = '')
    {
        $constraint = new StringContains($needle, false);
        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     *
     * @return void
     */
    public static function assertStringContainsStringIgnoringCase($needle, $haystack, $message = '')
    {
        $constraint = new StringContains($needle, true);
        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     *
     * @return void
     */
    public static function assertStringNotContainsString($needle, $haystack, $message = '')
    {
        $constraint = new LogicalNot(new StringContains($needle, false));
        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param string $needle
     * @param string $haystack
     * @param string $message
     *
     * @return void
     */
    public static function assertStringNotContainsStringIgnoringCase($needle, $haystack, $message = '')
    {
        $constraint = new LogicalNot(new StringContains($needle, true));
        static::assertThat($haystack, $constraint, $message);
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertFinite($actual, $message = '')
    {
        static::assertInternalType('float', $actual, $message);
        static::assertTrue(is_finite($actual), $message ? $message : "Failed asserting that $actual is finite.");
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertInfinite($actual, $message = '')
    {
        static::assertInternalType('float', $actual, $message);
        static::assertTrue(is_infinite($actual), $message ? $message : "Failed asserting that $actual is infinite.");
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertNan($actual, $message = '')
    {
        static::assertInternalType('float', $actual, $message);
        static::assertTrue(is_nan($actual), $message ? $message : "Failed asserting that $actual is nan.");
    }
}
