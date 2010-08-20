<?php

namespace Symfony\Component\Form\ValueTransformer;

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
        parent::configure();

        $this->addOption('input_timezone', 'UTC');
        $this->addOption('output_timezone', 'UTC');
        $this->addOption('pad', false);
        $this->addOption('fields', array('year', 'month', 'day', 'hour', 'minute', 'second'));
    }

    /**
     * Transforms a normalized date into a localized date string/array.
     *
     * @param  DateTime $dateTime  Normalized date.
     * @return string|array        Localized date array.
     */
    public function transform($dateTime)
    {
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
                $entry = (int)$entry;
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
        $inputTimezone = $this->getOption('input_timezone');
        $outputTimezone = $this->getOption('output_timezone');

        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type array, %s given', gettype($value)));
        }

        $dateTime = new \DateTime(sprintf(
            '%s-%s-%s %s:%s:%s %s',
            isset($value['year']) ? $value['year'] : 1970,
            isset($value['month']) ? $value['month'] : 1,
            isset($value['day']) ? $value['day'] : 1,
            isset($value['hour']) ? $value['hour'] : 0,
            isset($value['minute']) ? $value['minute'] : 0,
            isset($value['second']) ? $value['second'] : 0,
            $outputTimezone
        ));

        if ($inputTimezone != $outputTimezone) {
            $dateTime->setTimezone(new \DateTimeZone($inputTimezone));
        }

        return $dateTime;
    }
}