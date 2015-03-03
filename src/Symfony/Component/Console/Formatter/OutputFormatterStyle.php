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
 * Formatter style class for defining styles.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * @api
 */
class OutputFormatterStyle implements OutputFormatterStyleInterface
{
    private static $availableForegroundColors = array(
        'black', 'red', 'green', 'yellow',
        'blue', 'magenta', 'cyan', 'white',
    );
    private static $availableBackgroundColors = array(
        'black', 'red', 'green', 'yellow',
        'blue', 'magenta', 'cyan', 'white',
    );
    private static $availableOptions = array(
        'bold', 'underscore', 'blink',  'reverse',
        'conceal',
    );

    private $foreground;
    private $background;
    private $options = array();

    /**
     * Initializes output formatter style.
     *
     * @param string|null $foreground The style foreground color name
     * @param string|null $background The style background color name
     * @param array       $options    The style options
     *
     * @api
     */
    public function __construct($foreground = null, $background = null, array $options = array())
    {
        if (null !== $foreground) {
            $this->setForeground($foreground);
        }
        if (null !== $background) {
            $this->setBackground($background);
        }
        if (count($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * Gets style foreground color.
     *
     * @return string
     *
     * @api
     */
    public function getForeground()
    {
        return $this->foreground;
    }

    /**
     * Sets style foreground color.
     *
     * @param string|null $color The color name
     *
     * @throws \InvalidArgumentException When the color name isn't defined
     *
     * @api
     */
    public function setForeground($color = null)
    {
        if (null === $color) {
            $this->foreground = null;

            return;
        }

        if (!in_array($color, static::$availableForegroundColors)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid foreground color specified: "%s". Expected one of (%s)',
                $color,
                implode(', ', static::$availableForegroundColors)
            ));
        }

        $this->foreground = $color;
    }

    /**
     * Gets style background color.
     *
     * @return string
     *
     * @api
     */
    public function getBackground()
    {
        return $this->background;
    }

    /**
     * Sets style background color.
     *
     * @param string|null $color The color name
     *
     * @throws \InvalidArgumentException When the color name isn't defined
     *
     * @api
     */
    public function setBackground($color = null)
    {
        if (null === $color) {
            $this->background = null;

            return;
        }

        if (!in_array($color, static::$availableBackgroundColors)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid background color specified: "%s". Expected one of (%s)',
                $color,
                implode(', ', static::$availableBackgroundColors)
            ));
        }

        $this->background = $color;
    }

    /**
     * Gets style options.
     *
     * @return array
     *
     * @api
     */
    public function getOptions()
    {
        return count($this->options) ? $this->options : null;
    }

    /**
     * Sets some specific style option.
     *
     * @param string $option The option name
     *
     * @throws \InvalidArgumentException When the option name isn't defined
     *
     * @api
     */
    public function setOption($option)
    {
        if (!in_array($option, static::$availableOptions)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid option specified: "%s". Expected one of (%s)',
                $option,
                implode(', ', static::$availableOptions)
            ));
        }

        if (false === array_search($option, $this->options)) {
            $this->options[] = $option;
        }
    }

    /**
     * Unsets some specific style option.
     *
     * @param string $option The option name
     *
     * @throws \InvalidArgumentException When the option name isn't defined
     */
    public function unsetOption($option)
    {
        if (!in_array($option, static::$availableOptions)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid option specified: "%s". Expected one of (%s)',
                $option,
                implode(', ', static::$availableOptions)
            ));
        }

        $pos = array_search($option, $this->options);
        if (false !== $pos) {
            unset($this->options[$pos]);
        }
    }

    /**
     * Sets multiple style options at once.
     *
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = array();

        foreach ($options as $option) {
            $this->setOption($option);
        }
    }

    /**
     * Gets the style definition.
     *
     * @return string
     */
    public function getDefinition()
    {
        $definition = array();

        if (isset($this->foreground)) {
            $definition[] = 'fg='.$this->foreground;
        }

        if (isset($this->background)) {
            $definition[] = 'bg='.$this->background;
        }

        if (count($this->options)) {
            $definition[] = 'options='.implode(';', $this->options);
        }

        return implode(';', $definition);
    }
}
