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
 * Transforms between a date string and a DateTime object
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToStringTransformer extends BaseDateTimeTransformer
{
    private $format;

    public function __construct($inputTimezone = null, $outputTimezone = null, $format = 'Y-m-d H:i:s')
    {
        parent::__construct($inputTimezone, $outputTimezone);

        $this->format = $format;
    }

    /**
     * Transforms a DateTime object into a date string with the configured format
     * and timezone
     *
     * @param  DateTime $value  A DateTime object
     * @return string           A value as produced by PHP's date() function
     */
    public function transform($value)
    {
        if (null === $value) {
            return '';
        }

        if (!$value instanceof \DateTime) {
            throw new UnexpectedTypeException($value, '\DateTime');
        }

        $value->setTimezone(new \DateTimeZone($this->outputTimezone));

        return $value->format($this->format);
    }

    /**
     * Transforms a date string in the configured timezone into a DateTime object
     *
     * @param  string $value  A value as produced by PHP's date() function
     * @return DateTime       A DateTime object
     */
    public function reverseTransform($value)
    {
        if (empty($value)) {
            return null;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $outputTimezone = $this->outputTimezone;
        $inputTimezone = $this->inputTimezone;

        try {
            $dateTime = new \DateTime("$value $outputTimezone");

            if ($inputTimezone != $outputTimezone) {
                $dateTime->setTimeZone(new \DateTimeZone($inputTimezone));
            }

            return $dateTime;
        } catch (\Exception $e) {
            throw new TransformationFailedException('Expected a valid date string. ' . $e->getMessage(), 0, $e);
        }
    }
}