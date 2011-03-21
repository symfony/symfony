<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Formatter;

/**
 * Formatter interface for console output.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
interface OutputFormatterInterface
{
    /**
     * Sets the decorated flag.
     *
     * @param Boolean $decorated Whether to decorated the messages or not
     */
    function setDecorated($decorated);

    /**
     * Gets the decorated flag.
     *
     * @return Boolean true if the output will decorate messages, false otherwise
     */
    function isDecorated();

    /**
     * Sets a new style.
     *
     * @param   string                          $name     The style name
     * @param   OutputFormatterStyleInterface   $options  The style instance
     */
    function setStyle($name, OutputFormatterStyleInterface $style);

    /**
     * Checks if output formatter has style with specified name.
     *
     * @param   string  $name
     *
     * @return  boolean
     */
    function hasStyle($name);

    /**
     * Gets style options from style with specified name.
     *
     * @param   string  $name
     *
     * @return  OutputFormatterStyleInterface
     */
    function getStyle($name);

    /**
     * Formats a message according to the given styles.
     *
     * @param  string $message The message to style
     *
     * @return string The styled message
     */
    function format($message);
}
