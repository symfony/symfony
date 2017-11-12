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
 */
interface OutputFormatterStyleInterface
{
    /**
     * Sets style foreground color.
     *
     * @param string $color The color name
     */
    public function setForeground(string $color = null): void;

    /**
     * Sets style background color.
     *
     * @param string $color The color name
     */
    public function setBackground(string $color = null): void;

    /**
     * Sets some specific style option.
     *
     * @param string $option The option name
     */
    public function setOption(string $option): void;

    /**
     * Unsets some specific style option.
     *
     * @param string $option The option name
     */
    public function unsetOption(string $option): void;

    /**
     * Sets multiple style options at once.
     */
    public function setOptions(array $options): void;

    /**
     * Applies the style to a given text.
     *
     * @param string $text The text to style
     *
     * @return string
     */
    public function apply(string $text): string;
}
