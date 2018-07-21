<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;

/**
 * The TextHelper provides helpers for text formatting.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TextHelper
{
    /**
     * Returns the length of a string, using mb_strwidth if it is available.
     *
     * @param string $string The string to check its length
     *
     * @return int The length of the string
     */
    public static function strlen(string $string): int
    {
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return strlen($string);
        }

        return mb_strwidth($string, $encoding);
    }

    /**
     * Returns the subset of a string, using mb_substr if it is available.
     *
     * @param string   $string String to subset
     * @param int      $from   Start offset
     * @param int|null $length Length to read
     *
     * @return string The string subset
     */
    public static function substr(string $string, int $from, int $length = null): string
    {
        if (false === $encoding = mb_detect_encoding($string, null, true)) {
            return substr($string, $from, $length);
        }

        return mb_substr($string, $from, $length, $encoding);
    }

    /**
     * Returns a time difference in a human-readable format.
     *
     * @param int $secs Amount of seconds
     *
     * @return string The time difference
     */
    public static function formatTime(int $secs): string
    {
        static $timeFormats = array(
            array(0, '< 1 sec'),
            array(1, '1 sec'),
            array(2, 'secs', 1),
            array(60, '1 min'),
            array(120, 'mins', 60),
            array(3600, '1 hr'),
            array(7200, 'hrs', 3600),
            array(86400, '1 day'),
            array(172800, 'days', 86400),
        );

        foreach ($timeFormats as $index => $format) {
            if ($secs < $format[0]) {
                continue;
            }

            if (isset($timeFormats[$index + 1]) && $secs >= $timeFormats[$index + 1][0]) {
                continue;
            }

            if (2 == count($format)) {
                return $format[1];
            }

            return floor($secs / $format[2]).' '.$format[1];
        }
    }

    /**
     * Returns an amount of memory bytes in a human-readable format.
     *
     * @param int $memory Amount of bytes
     *
     * @return string The amount of memory
     */
    public static function formatMemory(int $memory): string
    {
        if ($memory >= 1024 * 1024 * 1024) {
            return sprintf('%.1f GiB', $memory / 1024 / 1024 / 1024);
        }

        if ($memory >= 1024 * 1024) {
            return sprintf('%.1f MiB', $memory / 1024 / 1024);
        }

        if ($memory >= 1024) {
            return sprintf('%d KiB', $memory / 1024);
        }

        return sprintf('%d B', $memory);
    }

    /**
     * Returns the length of a string, stripped of its console decoration characters.
     *
     * @param string $string The string to check its length
     *
     * @return int The length of the string
     */
    public static function strlenWithoutDecoration(OutputFormatterInterface $formatter, string $string): string
    {
        return self::strlen(self::removeDecoration($formatter, $string));
    }

    /**
     * Returns a string, stripped of its console decoration characters.
     *
     * @param OutputFormatterInterface $formatter
     * @param string                   $string
     *
     * @return string The string without console decoration characters
     */
    public static function removeDecoration(OutputFormatterInterface $formatter, string $string): string
    {
        $isDecorated = $formatter->isDecorated();
        $formatter->setDecorated(false);
        // remove <...> formatting
        $string = $formatter->format($string);
        // remove already formatted characters
        $string = preg_replace("/\033\[[^m]*m/", '', $string);
        $formatter->setDecorated($isDecorated);

        return $string;
    }
}
