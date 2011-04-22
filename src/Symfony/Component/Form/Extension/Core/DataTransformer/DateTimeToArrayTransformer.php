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
 * Transforms between a normalized time and a localized time string/array.
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
class DateTimeToArrayTransformer extends BaseDateTimeTransformer
{
    private $pad;

    private $fields;

    public function __construct($inputTimezone = null, $outputTimezone = null, $fields = null, $pad = false)
    {
        parent::__construct($inputTimezone, $outputTimezone);

        if (is_null($fields)) {
            $fields = array('year', 'month', 'day', 'hour', 'minute', 'second');
        }

        $this->fields = $fields;
        $this->pad =$pad;
    }

    /**
     * Transforms a normalized date into a localized date string/array.
     *
     * @param  DateTime $dateTime  Normalized date.
     * @return string|array        Localized date array.
     */
    public function transform($dateTime)
    {
        if (null === $dateTime) {
            return array_intersect_key(array(
                'year'    => '',
                'month'   => '',
                'day'     => '',
                'hour'    => '',
                'minute'  => '',
                'second'  => '',
            ), array_flip($this->fields));
        }

        if (!$dateTime instanceof \DateTime) {
            throw new UnexpectedTypeException($dateTime, '\DateTime');
        }

        $inputTimezone = $this->inputTimezone;
        $outputTimezone = $this->outputTimezone;

        if ($inputTimezone != $outputTimezone) {
            $dateTime->setTimezone(new \DateTimeZone($outputTimezone));
        }

        $result = array_intersect_key(array(
            'year'    => $dateTime->format('Y'),
            'month'   => $dateTime->format('m'),
            'day'     => $dateTime->format('d'),
            'hour'    => $dateTime->format('H'),
            'minute'  => $dateTime->format('i'),
            'second'  => $dateTime->format('s'),
        ), array_flip($this->fields));

        if (!$this->pad) {
            foreach ($result as &$entry) {
                // remove leading zeros
                $entry = (string)(int)$entry;
            }
        }

        return $result;
    }

    /**
     * Transforms a localized date string/array into a normalized date.
     *
     * @param  array $value  Localized date string/array
     * @return DateTime      Normalized date
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return null;
        }

        $inputTimezone = $this->inputTimezone;
        $outputTimezone = $this->outputTimezone;

        if (!is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        if (implode('', $value) === '') {
            return null;
        }

        $emptyFields = array();

        foreach ($this->fields as $field) {
            if (!isset($value[$field])) {
                $emptyFields[] = $field;
            }
        }

        if (count($emptyFields) > 0) {
            throw new TransformationFailedException(sprintf(
                    'The fields "%s" should not be empty', implode('", "', $emptyFields)));
        }

        try {
            $dateTime = new \DateTime(sprintf(
                '%s-%s-%s %s:%s:%s %s',
                empty($value['year']) ? '1970' : $value['year'],
                empty($value['month']) ? '1' : $value['month'],
                empty($value['day']) ? '1' : $value['day'],
                empty($value['hour']) ? '0' : $value['hour'],
                empty($value['minute']) ? '0' : $value['minute'],
                empty($value['second']) ? '0' : $value['second'],
                $outputTimezone
            ));
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), null, $e);
        }

        if ($inputTimezone != $outputTimezone) {
            $dateTime->setTimezone(new \DateTimeZone($inputTimezone));
        }

        return $dateTime;
    }
}