<?php

namespace Symfony\Component\Form\ValueTransformer;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use \Symfony\Component\Form\ValueTransformer\ValueTransformerException;

/**
 * Transforms between a normalized time and a localized time string/array.
 *
 * Options:
 *
 *  * "input": The type of the normalized format ("time" or "timestamp"). Default: "datetime"
 *  * "output": The type of the transformed format ("string" or "array"). Default: "string"
 *  * "format": The format of the time string ("short", "medium", "long" or "full"). Default: "short"
 *  * "locale": The locale of the localized string. Default: Result of Locale::getDefault()
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToArrayTransformer extends BaseDateTimeTransformer
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('input_timezone', 'UTC');
        $this->addOption('output_timezone', 'UTC');
        $this->addOption('pad', false);
        $this->addOption('fields', array('year', 'month', 'day', 'hour', 'minute', 'second'));

        parent::configure();
    }

    /**
     * Transforms a normalized date into a localized date string/array.
     *
     * @param  DateTime $dateTime  Normalized date.
     * @return string|array        Localized date array.
     */
    public function transform($dateTime)
    {
        if ($dateTime === null) {
            return array(
                'year'    => '',
                'month'   => '',
                'day'     => '',
                'hour'    => '',
                'minute'  => '',
                'second'  => '',
            );
        }

        if (!$dateTime instanceof \DateTime) {
            throw new \InvalidArgumentException('Expected value of type \DateTime');
        }

        $inputTimezone = $this->getOption('input_timezone');
        $outputTimezone = $this->getOption('output_timezone');

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
        ), array_flip($this->getOption('fields')));

        if (!$this->getOption('pad')) {
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
    public function reverseTransform($value, $originalValue)
    {
        if ($value === null) {
            return null;
        }

        $inputTimezone = $this->getOption('input_timezone');
        $outputTimezone = $this->getOption('output_timezone');

        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type array, %s given', gettype($value)));
        }

        if (implode('', $value) === '') {
            return null;
        }

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

        if ($inputTimezone != $outputTimezone) {
            $dateTime->setTimezone(new \DateTimeZone($inputTimezone));
        }

        return $dateTime;
    }
}