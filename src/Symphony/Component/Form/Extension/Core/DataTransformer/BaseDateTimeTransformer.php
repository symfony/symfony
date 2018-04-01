<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Core\DataTransformer;

use Symphony\Component\Form\DataTransformerInterface;
use Symphony\Component\Form\Exception\InvalidArgumentException;

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

    /**
     * @param string $inputTimezone  The name of the input timezone
     * @param string $outputTimezone The name of the output timezone
     *
     * @throws InvalidArgumentException if a timezone is not valid
     */
    public function __construct(string $inputTimezone = null, string $outputTimezone = null)
    {
        $this->inputTimezone = $inputTimezone ?: date_default_timezone_get();
        $this->outputTimezone = $outputTimezone ?: date_default_timezone_get();

        // Check if input and output timezones are valid
        try {
            new \DateTimeZone($this->inputTimezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Input timezone is invalid: %s.', $this->inputTimezone), $e->getCode(), $e);
        }

        try {
            new \DateTimeZone($this->outputTimezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(sprintf('Output timezone is invalid: %s.', $this->outputTimezone), $e->getCode(), $e);
        }
    }
}
