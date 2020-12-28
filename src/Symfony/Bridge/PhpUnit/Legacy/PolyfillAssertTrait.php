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

use PHPUnit\Framework\Constraint\LogicalNot;
use PHPUnit\Framework\Constraint\TraversableContains;

/**
 * This trait is @internal.
 */
trait PolyfillAssertTrait
{
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
