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

/**
 * Transforms between a timestamp and a DateTime object.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @extends BaseDateTimeTransformer<int|numeric-string>
 */
class DateTimeToTimestampTransformer extends BaseDateTimeTransformer
{
    public function transform(mixed $dateTime): ?int
    {
        if (null === $dateTime) {
            return null;
        }

        if (!$dateTime instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTimeInterface.');
        }

        return $dateTime->getTimestamp();
    }

    public function reverseTransform(mixed $value): ?\DateTime
    {
        if (null === $value) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new TransformationFailedException('Expected a numeric.');
        }

        try {
            $dateTime = new \DateTime();
            $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
            $dateTime->setTimestamp($value);

            if ($this->inputTimezone !== $this->outputTimezone) {
                $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
            }
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        return $dateTime;
    }
}
