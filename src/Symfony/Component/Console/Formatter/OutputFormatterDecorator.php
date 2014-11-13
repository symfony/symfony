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
 * The default formatter decorator.
 *
 * @author Christian Neff <christian.neff@gmail.com>
 *
 * @api
 */
class OutputFormatterDecorator implements OutputFormatterDecoratorInterface
{
    private static $foregroundColors = array(
        'black'     => array('set' => 30, 'unset' => 39),
        'red'       => array('set' => 31, 'unset' => 39),
        'green'     => array('set' => 32, 'unset' => 39),
        'yellow'    => array('set' => 33, 'unset' => 39),
        'blue'      => array('set' => 34, 'unset' => 39),
        'magenta'   => array('set' => 35, 'unset' => 39),
        'cyan'      => array('set' => 36, 'unset' => 39),
        'white'     => array('set' => 37, 'unset' => 39),
    );
    private static $backgroundColors = array(
        'black'     => array('set' => 40, 'unset' => 49),
        'red'       => array('set' => 41, 'unset' => 49),
        'green'     => array('set' => 42, 'unset' => 49),
        'yellow'    => array('set' => 43, 'unset' => 49),
        'blue'      => array('set' => 44, 'unset' => 49),
        'magenta'   => array('set' => 45, 'unset' => 49),
        'cyan'      => array('set' => 46, 'unset' => 49),
        'white'     => array('set' => 47, 'unset' => 49),
    );
    private static $options = array(
        'bold'          => array('set' => 1, 'unset' => 22),
        'underscore'    => array('set' => 4, 'unset' => 24),
        'blink'         => array('set' => 5, 'unset' => 25),
        'reverse'       => array('set' => 7, 'unset' => 27),
        'conceal'       => array('set' => 8, 'unset' => 28),
    );

    /**
     * {@inheritdoc}
     */
    public function decorate($text, OutputFormatterStyle $style)
    {
        $setCodes = array();
        $unsetCodes = array();

        if (null !== $foreground = $style->getForeground()) {
            $setCodes[] = self::$foregroundColors[$foreground]['set'];
            $unsetCodes[] = self::$foregroundColors[$foreground]['unset'];
        }
        if (null !== $background = $style->getBackground()) {
            $setCodes[] = self::$backgroundColors[$background]['set'];
            $unsetCodes[] = self::$backgroundColors[$background]['unset'];
        }
        if ($options = $style->getOptions()) {
            foreach ($options as $option) {
                $setCodes[] = self::$options[$option]['set'];
                $unsetCodes[] = self::$options[$option]['unset'];
            }
        }

        if (0 === count($setCodes)) {
            return $text;
        }

        return sprintf("\033[%sm%s\033[%sm", implode(';', $setCodes), $text, implode(';', $unsetCodes));
    }
}
