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
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface FormatterInterface
{
    /**
     * Formats a message within a section.
     *
     * @param string $section The section name
     * @param string $message The message
     * @param string $style   The style to apply to the section
     */
    public function formatSection($section, $message, $style = 'info'): string;

    /**
     * Formats a message as a block of text.
     *
     * @param string|array $messages The message to write in the block
     * @param string       $style    The style to apply to the whole block
     * @param bool         $large    Whether to return a large block
     */
    public function formatBlock($messages, $style, $large = false): string;

    /**
     * Returns an amount of seconds in a human-readable format.
     *
     * @param int $secs The amount of seconds
     */
    public function formatTime(int $secs): string;

    /**
     * Returns an amount of memory in a human-readable format.
     *
     * @param int $memory The amount of memory
     */
    public function formatMemory(int $memory): string;

    /**
     * Returns the length of a string, using mb_strwidth if it is available.
     *
     * @param string $string The string to check its length
     */
    public function strlen(string $string): int;

    /**
     * Returns the subset of a string, using mb_substr if it is available.
     *
     * @param string   $string The string to subset
     * @param int      $from   Start offset
     * @param int|null $length Length to read
     */
    public function substr(string $string, int $from, int $length = null): string;

    /**
     * Truncates a string to the given length.
     *
     * @param string $string The string to truncate
     * @param int    $length Length to read
     * @param string $suffix The string to append to result
     */
    public function truncate(string $string, int $length, string $suffix = '...'): string;

    /**
     * Removes the length of a string without its console decoration.
     *
     * @param OutputFormatterInterface $formatter An output formatter
     * @param string                   $string    The string to check its length
     */
    public function strlenWithoutDecoration(OutputFormatterInterface $formatter, string $string): string;

    /**
     * Removes console decoration from a string.
     *
     * @param OutputFormatterInterface $formatter An output formatter
     * @param string                   $string    The string to remove decoration from
     */
    public function removeDecoration(OutputFormatterInterface $formatter, string $string): string;
}
