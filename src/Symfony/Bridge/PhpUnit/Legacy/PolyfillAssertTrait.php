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
 * This trait is @internal.
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
        static::assertTrue(is_finite($actual), $message ?: "Failed asserting that $actual is finite.");
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertInfinite($actual, $message = '')
    {
        static::assertInternalType('float', $actual, $message);
        static::assertTrue(is_infinite($actual), $message ?: "Failed asserting that $actual is infinite.");
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public static function assertNan($actual, $message = '')
    {
        static::assertInternalType('float', $actual, $message);
        static::assertTrue(is_nan($actual), $message ?: "Failed asserting that $actual is nan.");
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertIsReadable($filename, $message = '')
    {
        static::assertInternalType('string', $filename, $message);
        static::assertTrue(is_readable($filename), $message ?: "Failed asserting that $filename is readable.");
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertNotIsReadable($filename, $message = '')
    {
        static::assertInternalType('string', $filename, $message);
        static::assertFalse(is_readable($filename), $message ?: "Failed asserting that $filename is not readable.");
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertIsNotReadable($filename, $message = '')
    {
        static::assertNotIsReadable($filename, $message);
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertIsWritable($filename, $message = '')
    {
        static::assertInternalType('string', $filename, $message);
        static::assertTrue(is_writable($filename), $message ?: "Failed asserting that $filename is writable.");
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertNotIsWritable($filename, $message = '')
    {
        static::assertInternalType('string', $filename, $message);
        static::assertFalse(is_writable($filename), $message ?: "Failed asserting that $filename is not writable.");
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertIsNotWritable($filename, $message = '')
    {
        static::assertNotIsWritable($filename, $message);
    }

    /**
     * @param string $directory
     * @param string $message
     *
     * @return void
     */
    public static function assertDirectoryExists($directory, $message = '')
    {
        static::assertInternalType('string', $directory, $message);
        static::assertTrue(is_dir($directory), $message ?: "Failed asserting that $directory exists.");
    }

    /**
     * @param string $directory
     * @param string $message
     *
     * @return void
     */
    public static function assertDirectoryNotExists($directory, $message = '')
    {
        static::assertInternalType('string', $directory, $message);
        static::assertFalse(is_dir($directory), $message ?: "Failed asserting that $directory does not exist.");
    }

    /**
     * @param string $directory
     * @param string $message
     *
     * @return void
     */
    public static function assertDirectoryDoesNotExist($directory, $message = '')
    {
        static::assertDirectoryNotExists($directory, $message);
    }

    /**
     * @param string $directory
     * @param string $message
     *
     * @return void
     */
    public static function assertDirectoryIsReadable($directory, $message = '')
    {
        static::assertDirectoryExists($directory, $message);
        static::assertIsReadable($directory, $message);
    }

    /**
     * @param string $directory
     * @param string $message
     *
     * @return void
     */
    public static function assertDirectoryNotIsReadable($directory, $message = '')
    {
        static::assertDirectoryExists($directory, $message);
        static::assertNotIsReadable($directory, $message);
    }

    /**
     * @param string $directory
     * @param string $message
     *
     * @return void
     */
    public static function assertDirectoryIsNotReadable($directory, $message = '')
    {
        static::assertDirectoryNotIsReadable($directory, $message);
    }

    /**
     * @param string $directory
     * @param string $message
     *
     * @return void
     */
    public static function assertDirectoryIsWritable($directory, $message = '')
    {
        static::assertDirectoryExists($directory, $message);
        static::assertIsWritable($directory, $message);
    }

    /**
     * @param string $directory
     * @param string $message
     *
     * @return void
     */
    public static function assertDirectoryNotIsWritable($directory, $message = '')
    {
        static::assertDirectoryExists($directory, $message);
        static::assertNotIsWritable($directory, $message);
    }

    /**
     * @param string $directory
     * @param string $message
     *
     * @return void
     */
    public static function assertDirectoryIsNotWritable($directory, $message = '')
    {
        static::assertDirectoryNotIsWritable($directory, $message);
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertFileExists($filename, $message = '')
    {
        static::assertInternalType('string', $filename, $message);
        static::assertTrue(file_exists($filename), $message ?: "Failed asserting that $filename exists.");
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertFileNotExists($filename, $message = '')
    {
        static::assertInternalType('string', $filename, $message);
        static::assertFalse(file_exists($filename), $message ?: "Failed asserting that $filename does not exist.");
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertFileDoesNotExist($filename, $message = '')
    {
        static::assertFileNotExists($filename, $message);
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertFileIsReadable($filename, $message = '')
    {
        static::assertFileExists($filename, $message);
        static::assertIsReadable($filename, $message);
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertFileNotIsReadable($filename, $message = '')
    {
        static::assertFileExists($filename, $message);
        static::assertNotIsReadable($filename, $message);
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertFileIsNotReadable($filename, $message = '')
    {
        static::assertFileNotIsReadable($filename, $message);
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertFileIsWritable($filename, $message = '')
    {
        static::assertFileExists($filename, $message);
        static::assertIsWritable($filename, $message);
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertFileNotIsWritable($filename, $message = '')
    {
        static::assertFileExists($filename, $message);
        static::assertNotIsWritable($filename, $message);
    }

    /**
     * @param string $filename
     * @param string $message
     *
     * @return void
     */
    public static function assertFileIsNotWritable($filename, $message = '')
    {
        static::assertFileNotIsWritable($filename, $message);
    }

    /**
     * @param string $pattern
     * @param string $string
     * @param string $message
     *
     * @return void
     */
    public static function assertMatchesRegularExpression($pattern, $string, $message = '')
    {
        static::assertRegExp($pattern, $string, $message);
    }

    /**
     * @param string $pattern
     * @param string $string
     * @param string $message
     *
     * @return void
     */
    public static function assertDoesNotMatchRegularExpression($pattern, $string, $message = '')
    {
        static::assertNotRegExp($pattern, $string, $message);
    }
}
