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

use Symfony\Component\Form\Configurable;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a date string and a DateTime object
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class DateTimeToStringTransformer extends Configurable implements ValueTransformerInterface
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('input_timezone', date_default_timezone_get());
        $this->addOption('output_timezone', date_default_timezone_get());
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