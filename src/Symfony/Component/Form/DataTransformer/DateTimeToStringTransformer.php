<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\DataTransformer;

use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a date string and a DateTime object
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToStringTransformer implements DataTransformerInterface
{

    private $input_timezone;

    private $output_timezone;

    private $format;
    
    public function __construct($format='Y-m-d H:i:s', $input_timezone = null, $output_timezone = null)
    {
        if(is_null($input_timezone))
        {
            $input_timezone = date_default_timezone_get();
        }
        if(is_null($output_timezone))
        {
            $output_timezone = date_default_timezone_get();
        }

        $this->format = $format;
        $this->input_timezone = $input_timezone;
        $this->output_timezone = $output_timezone;
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

        $value->setTimezone(new \DateTimeZone($this->getOption('output_timezone')));

        return $value->format($this->getOption('format'));
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

        $outputTimezone = $this->getOption('output_timezone');
        $inputTimezone = $this->getOption('input_timezone');

        try {
            $dateTime = new \DateTime("$value $outputTimezone");

            if ($inputTimezone != $outputTimezone) {
                $dateTime->setTimeZone(new \DateTimeZone($inputTimezone));
            }

            return $dateTime;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Expected a valid date string. ' . $e->getMessage(), 0, $e);
        }
    }
}