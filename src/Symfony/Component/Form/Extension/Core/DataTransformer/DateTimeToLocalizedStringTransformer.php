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

use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a normalized time and a localized time string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @deprecated The Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToLocalizedStringTransformer class is deprecated since version 4.1 and will be removed in 5.0. Use the Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeImmutableToLocalizedStringTransformer class instead.
 */
class DateTimeToLocalizedStringTransformer extends BaseDateTimeTransformer
{
    use DateTimeImmutableTransformerDecoratorTrait;

    /**
     * @see BaseDateTimeTransformer::formats for available format options
     *
     * @param string $inputTimezone  The name of the input timezone
     * @param string $outputTimezone The name of the output timezone
     * @param int    $dateFormat     The date format
     * @param int    $timeFormat     The time format
     * @param int    $calendar       One of the \IntlDateFormatter calendar constants
     * @param string $pattern        A pattern to pass to \IntlDateFormatter
     *
     * @throws UnexpectedTypeException If a format is not supported or if a timezone is not a string
     */
    public function __construct(string $inputTimezone = null, string $outputTimezone = null, int $dateFormat = null, int $timeFormat = null, int $calendar = \IntlDateFormatter::GREGORIAN, string $pattern = null)
    {
        parent::__construct($inputTimezone, $outputTimezone);
        $this->decorated = new DateTimeImmutableToLocalizedStringTransformer($inputTimezone, $outputTimezone, $dateFormat, $timeFormat, $calendar, $pattern);
    }

    /**
     * Returns a preconfigured IntlDateFormatter instance.
     *
     * @param bool $ignoreTimezone Use UTC regardless of the configured timezone
     *
     * @return \IntlDateFormatter
     *
     * @throws TransformationFailedException in case the date formatter can not be constructed
     */
    protected function getIntlDateFormatter($ignoreTimezone = false)
    {
        return \Closure::bind(function ($ignoreTimezone) {
            return $this->getIntlDateFormatter($ignoreTimezone);
        }, $this->decorated, DateTimeImmutableToLocalizedStringTransformer::class)($ignoreTimezone);
    }

    /**
     * Checks if the pattern contains only a date.
     *
     * @return bool
     */
    protected function isPatternDateOnly()
    {
        return \Closure::bind(function () {
            return $this->getIntlDateFormatter();
        }, $this->decorated, DateTimeImmutableToLocalizedStringTransformer::class)();
    }
}
