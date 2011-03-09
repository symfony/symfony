<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\ValueTransformer;

use Symfony\Component\Form\Configurable;

abstract class BaseDateTimeTransformer extends Configurable implements ValueTransformerInterface
{
    const NONE   = 'none';
    const FULL   = 'full';
    const LONG   = 'long';
    const MEDIUM = 'medium';
    const SHORT  = 'short';

    protected static $formats = array(
        self::NONE,
        self::FULL,
        self::LONG,
        self::MEDIUM,
        self::SHORT,
    );

    /**
     * Returns the appropriate IntLDateFormatter constant for the given format
     *
     * @param  string $format  One of "short", "medium", "long" and "full"
     * @return integer
     */
    protected function getIntlFormatConstant($format)
    {
        switch ($format) {
            case self::FULL:
                return \IntlDateFormatter::FULL;
            case self::LONG:
                return \IntlDateFormatter::LONG;
            case self::SHORT:
                return \IntldateFormatter::SHORT;
            case self::MEDIUM:
                return \IntlDateFormatter::MEDIUM;
            case self::NONE:
            default:
                return \IntlDateFormatter::NONE;
        }
    }
}