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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a normalized time and a localized time string
 *
 * Options:
 *
 *  * "input": The type of the normalized format ("time" or "timestamp"). Default: "datetime"
 *  * "output": The type of the transformed format ("string" or "array"). Default: "string"
 *  * "format": The format of the time string ("short", "medium", "long" or "full"). Default: "short"
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToLocalizedStringTransformer extends BaseDateTimeTransformer
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('date_format', self::MEDIUM);
        $this->addOption('time_format', self::SHORT);
        $this->addOption('input_timezone', 'UTC');
        $this->addOption('output_timezone', 'UTC');

        if (!in_array($this->getOption('date_format'), self::$formats, true)) {
            throw new \InvalidArgumentException(sprintf('The option "date_format" is expected to be one of "%s". Is "%s"', implode('", "', self::$formats), $this->getOption('time_format')));
        }

        if (!in_array($this->getOption('time_format'), self::$formats, true)) {
            throw new \InvalidArgumentException(sprintf('The option "time_format" is expected to be one of "%s". Is "%s"', implode('", "', self::$formats), $this->getOption('time_format')));
        }

        parent::configure();
    }

    /**
     * Transforms a normalized date into a localized date string/array.
     *
     * @param  DateTime $dateTime  Normalized date.
     * @return string|array        Localized date string/array.
     */
    public function transform($dateTime)
    {
        if (null === $dateTime) {
            return '';
        }

        if (!$dateTime instanceof \DateTime) {
            throw new UnexpectedTypeException($dateTime, '\DateTime');
        }

        $inputTimezone = $this->getOption('input_timezone');

        // convert time to UTC before passing it to the formatter
        if ('UTC' != $inputTimezone) {
            $dateTime->setTimezone(new \DateTimeZone('UTC'));
        }

        $value = $this->getIntlDateFormatter()->format((int)$dateTime->format('U'));

        if (intl_get_error_code() != 0) {
            throw new TransformationFailedException(intl_get_error_message());
        }

        return $value;
    }

    /**
     * Transforms a localized date string/array into a normalized date.
     *
     * @param  string|array $value Localized date string/array
     * @return DateTime Normalized date
     */
    public function reverseTransform($value)
    {
        $inputTimezone = $this->getOption('input_timezone');

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if ('' === $value) {
            return null;
        }

        $timestamp = $this->getIntlDateFormatter()->parse($value);

        if (intl_get_error_code() != 0) {
            throw new TransformationFailedException(intl_get_error_message());
        }

        // read timestamp into DateTime object - the formatter delivers in UTC
        $dateTime = new \DateTime(sprintf('@%s UTC', $timestamp));

        if ('UTC' != $inputTimezone) {
            $dateTime->setTimezone(new \DateTimeZone($inputTimezone));
        }

        return $dateTime;
    }

    /**
     * Returns a preconfigured IntlDateFormatter instance
     *
     * @return \IntlDateFormatter
     */
    protected function getIntlDateFormatter()
    {
        $dateFormat = $this->getIntlFormatConstant($this->getOption('date_format'));
        $timeFormat = $this->getIntlFormatConstant($this->getOption('time_format'));
        $timezone = $this->getOption('output_timezone');

        return new \IntlDateFormatter(\Locale::getDefault(), $dateFormat, $timeFormat, $timezone);
    }
}