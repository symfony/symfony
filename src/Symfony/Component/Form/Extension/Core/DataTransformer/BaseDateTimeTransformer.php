<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;

abstract class BaseDateTimeTransformer implements DataTransformerInterface
{
    protected static $formats = array(
        \IntlDateFormatter::NONE,
        \IntlDateFormatter::FULL,
        \IntlDateFormatter::LONG,
        \IntlDateFormatter::MEDIUM,
        \IntlDateFormatter::SHORT,
    );

    protected $inputTimezone;

    protected $outputTimezone;

    public function __construct($inputTimezone = null, $outputTimezone = null)
    {
        $this->inputTimezone = $inputTimezone ?: date_default_timezone_get();
        $this->outputTimezone = $outputTimezone ?: date_default_timezone_get();
    }
}