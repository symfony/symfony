<?php

namespace Symfony\Component\Form\ValueTransformer;

use \Symfony\Component\Form\ValueTransformer\ValueTransformerException;

/**
 * Transforms between a timestamp and a DateTime object
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class TimestampToDateTimeTransformer extends BaseValueTransformer
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('input_timezone', 'UTC');
        $this->addOption('output_timezone', 'UTC');
    }

    /**
     * Transforms a timestamp in the configured timezone into a DateTime object
     *
     * @param  string $value  A value as produced by PHP's date() function
     * @return DateTime       A DateTime object
     */
    public function transform($value)
    {
        $inputTimezone = $this->getOption('input_timezone');
        $outputTimezone = $this->getOption('output_timezone');

        try {
            $dateTime = new \DateTime("@$value $inputTimezone");

            if ($inputTimezone != $outputTimezone) {
                $dateTime->setTimezone(new \DateTimeZone($outputTimezone));
            }

            return $dateTime;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Expected a valid timestamp. ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Transforms a DateTime object into a timestamp in the configured timezone
     *
     * @param  DateTime $value  A DateTime object
     * @return integer          A timestamp
     */
    public function reverseTransform($value)
    {
        if (!$value instanceof \DateTime) {
            throw new \InvalidArgumentException('Expected value of type \DateTime');
        }

        $value->setTimezone(new \DateTimeZone($this->getOption('input_timezone')));

        return (int)$value->format('U');
    }
}