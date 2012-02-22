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
use Symfony\Component\Form\Exception\UnexpectedTypeException;

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

    protected $dateTimeClass;

    protected $dateTimeZoneClass;

    /**
     * Constructor.
     *
     * @param string $inputTimezone     The name of the input timezone
     * @param string $outputTimezone    The name of the output timezone
     * @param string $dateTimeClass     The date time class (must be compatible with DateTime)
     * @param string $dateTimeZoneClass The date time zone class (must be compatible with DateTimeZone)
     *
     * @throws UnexpectedTypeException if a timezone is not a string
     */
    public function __construct(
        $inputTimezone = null,
        $outputTimezone = null,
        $dateTimeClass = null,
        $dateTimeZoneClass = null
    )
    {
        if (!is_string($inputTimezone) && null !== $inputTimezone) {
            throw new UnexpectedTypeException($inputTimezone, 'string');
        }

        if (!is_string($outputTimezone) && null !== $outputTimezone) {
            throw new UnexpectedTypeException($outputTimezone, 'string');
        }

        if ($dateTimeClass && !is_a($dateTimeClass, 'DateTime', true)) {
            throw new UnexpectedTypeException($dateTimeClass, 'DateTime');
        }

        if ($dateTimeZoneClass && !is_a($dateTimeZoneClass, 'DateTimeZone', true)) {
            throw new UnexpectedTypeException($dateTimeZoneClass, 'DateTimeZone');
        }

        $this->inputTimezone = $inputTimezone ?: date_default_timezone_get();
        $this->outputTimezone = $outputTimezone ?: date_default_timezone_get();
        $this->dateTimeClass = $dateTimeClass ?: 'DateTime';
        $this->dateTimeZoneClass = $dateTimeZoneClass ?: 'DateTimeZone';
    }

    protected function createDate($dateString, \DateTimeZone $timezone)
    {
        $date = new $this->dateTimeClass($dateString, $timezone);

        return $date;
    }

    protected function createTimezone($timezoneName)
    {
        return new $this->dateTimeZoneClass($timezoneName);
    }
}
