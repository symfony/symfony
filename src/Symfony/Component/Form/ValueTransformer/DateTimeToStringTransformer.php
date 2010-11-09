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
 * Transforms between a date string and a DateTime object
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToStringTransformer extends BaseValueTransformer
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('input_timezone', 'UTC');
        $this->addOption('output_timezone', 'UTC');
        $this->addOption('format', 'Y-m-d H:i:s');

        parent::configure();
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
        if ($value === null) {
            return '';
        }

        if (!$value instanceof \DateTime) {
            throw new \InvalidArgumentException('Expected value of type \DateTime');
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
    public function reverseTransform($value, $originalValue)
    {
        if ($value === '') {
            return null;
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