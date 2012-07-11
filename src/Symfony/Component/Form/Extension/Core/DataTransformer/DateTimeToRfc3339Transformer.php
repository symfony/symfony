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

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DateTimeToRfc3339Transformer extends BaseDateTimeTransformer
{
    /**
     * {@inheritDoc}
     */
    public function transform($dateTime)
    {
        if (null === $dateTime) {
            return '';
        }

        if (!$dateTime instanceof \DateTime) {
            throw new UnexpectedTypeException($dateTime, '\DateTime');
        }

        if ($this->inputTimezone !== $this->outputTimezone) {
            $dateTime = clone $dateTime;
            $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
        }

        return preg_replace('/\+00:00$/', 'Z', $dateTime->format('c'));
    }

    /**
     * {@inheritDoc}
     */
    public function reverseTransform($rfc3339)
    {
        if (!is_string($rfc3339)) {
            throw new UnexpectedTypeException($rfc3339, 'string');
        }

        if ('' === $rfc3339) {
            return null;
        }


        $dateTime = new \DateTime($rfc3339);

        if ($this->outputTimezone !== $this->inputTimezone) {
            try {
                $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
            } catch (\Exception $e) {
                throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $rfc3339, $matches)) {
            if (!checkdate($matches[2], $matches[3], $matches[1])) {
                throw new TransformationFailedException(sprintf(
                    'The date "%s-%s-%s" is not a valid date.',
                    $matches[1],
                    $matches[2],
                    $matches[3]
                ));
            }
        }

        return $dateTime;
    }
}
