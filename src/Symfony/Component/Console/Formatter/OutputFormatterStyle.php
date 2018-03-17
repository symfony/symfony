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

use Symfony\Component\Console\Exception\InvalidArgumentException;

/**
 * Formatter style class for defining styles.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 * @author Rob Frawley 2nd <rmf@src.run>
 */
class OutputFormatterStyle implements OutputFormatterStyleInterface
{
    private const FOREGROUND_COLOR_FORMATS = array(
        '04-bit-normal' => '3%d',
        '04-bit-bright' => '9%d',
        '08-bit' => '38;5;%d',
        '24-bit' => '38;2;%d;%d;%d',
    );
    private const FOREGROUND_COLOR_RANGE = array(0, 255);
    private const FOREGROUND_COLOR_UNSET = 39;
    private const FOREGROUND_COLOR_NAMES = array(
        'black' => 0,
        'red' => 1,
        'green' => 2,
        'yellow' => 3,
        'blue' => 4,
        'magenta' => 5,
        'cyan' => 6,
        'white' => 7,
        'default' => 9,
    );
    private const BACKGROUND_COLOR_FORMATS = array(
        '04-bit-normal' => '4%d',
        '04-bit-bright' => '10%d',
        '08-bit' => '48;5;%d',
        '24-bit' => '48;2;%d;%d;%d',
    );
    private const BACKGROUND_COLOR_RANGE = array(0, 255);
    private const BACKGROUND_COLOR_UNSET = 49;
    private const BACKGROUND_COLOR_NAMES = array(
        'black' => 0,
        'red' => 1,
        'green' => 2,
        'yellow' => 3,
        'blue' => 4,
        'magenta' => 5,
        'cyan' => 6,
        'white' => 7,
        'default' => 9,
    );
    private const FORMATTING_OPTIONS = array(
        'bold' => array('set' => 1, 'unset' => 22),
        'underscore' => array('set' => 4, 'unset' => 24),
        'blink' => array('set' => 5, 'unset' => 25),
        'reverse' => array('set' => 7, 'unset' => 27),
        'conceal' => array('set' => 8, 'unset' => 28),
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
     */
    public function __construct(string $foreground = null, string $background = null, array $options = array())
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
     * Sets style foreground color.
     *
     * @param string|null $color The color name
     *
     * @throws InvalidArgumentException When the color name isn't defined
     */
    public function setForeground($color = null)
    {
        if (null === $color) {
            $this->foreground = null;

            return;
        }

        if (null === $definition = self::buildColorDefinition($color, 'foreground')) {
            throw new InvalidArgumentException(self::getInputColorExcMessage($color, 'foreground'));
        }

        $this->foreground = $definition;
    }

    /**
     * Sets style background color.
     *
     * @param string|null $color The color name
     *
     * @throws InvalidArgumentException When the color name isn't defined
     */
    public function setBackground($color = null)
    {
        if (null === $color) {
            $this->background = null;

            return;
        }

        if (null === $definition = self::buildColorDefinition($color, 'background')) {
            throw new InvalidArgumentException(self::getInputColorExcMessage($color, 'background'));
        }

        $this->background = $definition;
    }

    /**
     * Sets some specific style option.
     *
     * @param string $option The option name
     *
     * @throws InvalidArgumentException When the option name isn't defined
     */
    public function setOption($option)
    {
        if (!isset(self::FORMATTING_OPTIONS[$option])) {
            throw new InvalidArgumentException(self::getInputOptionExcMessage($option));
        }

        if (!in_array(self::FORMATTING_OPTIONS[$option], $this->options)) {
            $this->options[] = self::FORMATTING_OPTIONS[$option];
        }
    }

    /**
     * Unsets some specific style option.
     *
     * @param string $option The option name
     *
     * @throws InvalidArgumentException When the option name isn't defined
     */
    public function unsetOption($option)
    {
        if (!isset(self::FORMATTING_OPTIONS[$option])) {
            throw new InvalidArgumentException(self::getInputOptionExcMessage($option));
        }

        $pos = array_search(self::FORMATTING_OPTIONS[$option], $this->options);
        if (false !== $pos) {
            unset($this->options[$pos]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        $this->options = array();

        foreach ($options as $option) {
            $this->setOption($option);
        }
    }

    /**
     * Applies the style to a given text.
     *
     * @param string $text The text to style
     *
     * @return string
     */
    public function apply($text)
    {
        $setCodes = array();
        $unsetCodes = array();

        if (null !== $this->foreground) {
            $setCodes[] = $this->foreground['set'];
            $unsetCodes[] = $this->foreground['unset'];
        }
        if (null !== $this->background) {
            $setCodes[] = $this->background['set'];
            $unsetCodes[] = $this->background['unset'];
        }
        if (count($this->options)) {
            foreach ($this->options as $option) {
                $setCodes[] = $option['set'];
                $unsetCodes[] = $option['unset'];
            }
        }

        if (0 === count($setCodes)) {
            return $text;
        }

        return sprintf("\033[%sm%s\033[%sm", implode(';', $setCodes), $text, implode(';', $unsetCodes));
    }

    /**
     * @param string $input
     * @param string $context
     *
     * @return array|null
     */
    private static function buildColorDefinition(string $input, string $context): ?array
    {
        if (null !== $inst = self::createColorDef24Bit($input)) {
            return self::finalizeColorDefinition($inst, $context);
        }

        if (null !== $inst = self::createColorDef08Bit($input, self::getColorConstForContext($context, 'range'))) {
            return self::finalizeColorDefinition($inst, $context);
        }

        if (null !== $inst = self::createColorDef04Bit($input, self::getColorConstForContext($context, 'names'))) {
            return self::finalizeColorDefinition($inst, $context);
        }

        return null;
    }

    /**
     * @param string $color
     *
     * @return array|null
     */
    private static function createColorDef24Bit(string $color): ?array
    {
        $match = self::matchColorStr('/^mode\-(?<r>[0-9]+),(?<b>[0-9]+),(?<g>[0-9]+)$/', $color);
        $level = function (int $coordinate): bool {
            return $coordinate >= 0 && $coordinate <= 255;
        };

        if (null !== $match && $level($match['r']) && $level($match['b']) && $level($match['g'])) {
            return array(
                'type' => '24-bit',
                'args' => array($match['r'], $match['b'], $match['g']),
            );
        }

        return null;
    }

    /**
     * @param string $color
     * @param array  $range
     *
     * @return array|null
     */
    private static function createColorDef08Bit(string $color, array $range): ?array
    {
        $match = self::matchColorStr('/^mode\-(?<name>[0-9]+)$/', $color);

        if (null !== $match && $match['name'] >= $range[0] && $match['name'] <= $range[1]) {
            return array(
                'type' => '08-bit',
                'args' => array($match['name']),
            );
        }

        return null;
    }

    /**
     * @param string $color
     * @param array  $allow
     *
     * @return array|null
     */
    private static function createColorDef04Bit(string $color, array $allow): ?array
    {
        $match = self::matchColorStr('/^(?<type>normal|bright)\-(?<name>[a-z]+)$/', $color);

        if (null !== $match && isset($allow[$match['name']])) {
            return array(
                'type' => sprintf('04-bit-%s', $match['type']),
                'args' => array($allow[$match['name']]),
            );
        }

        if (isset($allow[$color])) {
            return array(
                'type' => '04-bit-normal',
                'args' => array($allow[$color]),
            );
        }

        return null;
    }

    /**
     * @param array  $partial
     * @param string $context
     *
     * @return array
     */
    private static function finalizeColorDefinition(array $partial, string $context)
    {
        return $partial + array(
            'unset' => self::getColorConstForContext($context, 'unset'),
            'set' => vsprintf(
                self::getColorConstForContext($context, 'formats')[$partial['type']], $partial['args']
            ),
        );
    }

    /**
     * @param string $regex
     * @param string $color
     *
     * @return array|null
     */
    private static function matchColorStr(string $regex, string $color): ?array
    {
        if (1 === preg_match($regex, $color, $match)) {
            return $match;
        }

        return null;
    }

    /**
     * @param string $color
     * @param string $context
     *
     * @return string
     */
    private static function getInputColorExcMessage(string $color, string $context): string
    {
        $format = 'Invalid %s color "%s" specified. Accepted colors include 24-bit colors as "mode-<int>,<int>,<int>" '
                .'(RGB), 8-bit colors as "mode-<int>" (0-255), and 4-bit colors as "<string>", "default-<name>", or '
                .'"bright-<name>". Accepted 4-bit color names: %s.';

        return vsprintf($format, array(
            $context,
            $color,
            self::getFormattedArrayKeysFlattened(self::getColorConstForContext($context, 'names')),
        ));
    }

    /**
     * @param string $option
     *
     * @return string
     */
    private static function getInputOptionExcMessage(string $option): string
    {
        return vsprintf('Invalid option specified: "%s". Accepted options names: %s.', array(
            $option,
            self::getFormattedArrayKeysFlattened(self::FORMATTING_OPTIONS),
        ));
    }

    /**
     * @param string[] $array
     *
     * @return string
     */
    private static function getFormattedArrayKeysFlattened(array $array): string
    {
        return implode(', ', array_map(function (string $string): string {
            return sprintf('"%s"', $string);
        }, array_keys($array)));
    }

    /**
     * @param string $context
     * @param string $name
     *
     * @return mixed
     */
    private static function getColorConstForContext(string $context, string $name)
    {
        return constant(strtoupper(sprintf('self::%s_COLOR_%s', $context, $name)));
    }
}
