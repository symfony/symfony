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
 * Formatter style interface for defining styles.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * @api
 */
interface OutputFormatterStyleInterface
{
    /**
     * Sets style foreground color.
     *
     * @param   string  $color  color name
     *
     * @api
     */
    function setForeground($color = null);

    /**
     * Gets style foreground color.
     *
     * @return string|null
     */
    function getForeground();

    /**
     * Sets style background color.
     *
     * @param   string  $color  color name
     *
     * @api
     */
    function setBackground($color = null);

    /**
     * Gets style background color.
     *
     * @return string|null
     */
    function getBackground();

    /**
     * Sets some specific style option.
     *
     * @param   string  $option     option name
     *
     * @api
     */
    function setOption($option);

    /**
     * Unsets some specific style option.
     *
     * @param   string  $option     option name
     */
    function unsetOption($option);

    /**
     * Sets multiple style options at once.
     *
     * @param   array   $options
     */
    function setOptions(array $options);

    /**
     * Gets specific style options.
     *
     * @return array
     */
    function getOptions();

    /**
     * Gets terminal colorization sequence.
     *
     * @return string
     */
    function getTerminalSequence();

    /**
     * Applies the style to a given text.
     *
     * @param string $text The text to style
     *
     * @return string
     */
    function apply($text);
}
