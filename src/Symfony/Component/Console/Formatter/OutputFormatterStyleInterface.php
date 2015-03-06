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
     * Gets style foreground color.
     *
     * @return string
     *
     * @api
     */
    public function getForeground();

    /**
     * Sets style foreground color.
     *
     * @param string $color The color name
     *
     * @api
     */
    public function setForeground($color = null);

    /**
     * Gets style background color.
     *
     * @return string
     *
     * @api
     */
    public function getBackground();

    /**
     * Sets style background color.
     *
     * @param string $color The color name
     *
     * @api
     */
    public function setBackground($color = null);

    /**
     * Gets style options.
     *
     * @return array
     *
     * @api
     */
    public function getOptions();

    /**
     * Sets some specific style option.
     *
     * @param string $option The option name
     *
     * @api
     */
    public function setOption($option);

    /**
     * Unsets some specific style option.
     *
     * @param string $option The option name
     */
    public function unsetOption($option);

    /**
     * Sets multiple style options at once.
     *
     * @param array $options
     */
    public function setOptions(array $options);

    /**
     * Gets the style definition.
     *
     * @return string
     */
    public function getDefinition();

    /**
     * Applies the style to a given text.
     *
     * @param string $text The text to style
     * @param OutputFormatterDecorator $decorator The decorator to use or NULL to use the default decorator
     *
     * @return string
     */
    public function apply($text, OutputFormatterDecorator $decorator = null);
}
