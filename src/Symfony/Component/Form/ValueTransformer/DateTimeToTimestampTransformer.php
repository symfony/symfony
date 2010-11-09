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
 * Transforms between a timestamp and a DateTime object
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToTimestampTransformer extends BaseValueTransformer
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('input_timezone', 'UTC');
        $this->addOption('output_timezone', 'UTC');

        parent::configure();
    }

    /**
     * Transforms a DateTime object into a timestamp in the configured timezone
     *
     * @param  DateTime $value  A DateTime object
     * @return integer          A timestamp
     */
    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof \DateTime) {
            throw new \InvalidArgumentException('Expected value of type \DateTime');
        }

        $value->setTimezone(new \DateTimeZone($this->getOption('output_timezone')));

        return (int)$value->format('U');
    }

    /**
     * Transforms a timestamp in the configured timezone into a DateTime object
     *
     * @param  string $value  A value as produced by PHP's date() function
     * @return DateTime       A DateTime object
     */
    public function reverseTransform($value, $originalValue)
    {
        if ($value === null) {
            return null;
        }

        $outputTimezone = $this->getOption('output_timezone');
        $inputTimezone = $this->getOption('input_timezone');

        try {
            $dateTime = new \DateTime("@$value $outputTimezone");

            if ($inputTimezone != $outputTimezone) {
                $dateTime->setTimezone(new \DateTimeZone($inputTimezone));
            }

            return $dateTime;
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Expected a valid timestamp. ' . $e->getMessage(), 0, $e);
        }
    }
}