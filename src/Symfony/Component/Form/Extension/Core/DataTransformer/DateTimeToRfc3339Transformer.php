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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DateTimeToRfc3339Transformer extends BaseDateTimeTransformer
{
    /**
     * {@inheritdoc}
     */
    public function transform($dateTime)
    {
        if (null === $dateTime) {
            return '';
        }

        if (!$dateTime instanceof \DateTime && !$dateTime instanceof \DateTimeImmutable) {
            throw new TransformationFailedException('Expected a \DateTime or \DateTimeImmutable.');
        }

        if ($this->inputTimezone !== $this->outputTimezone) {
            $dateTime = clone $dateTime;
            $dateTime = $dateTime->setTimezone(new \DateTimeZone($this->outputTimezone));
        }

        return preg_replace('/\+00:00$/', 'Z', $dateTime->format('c'));
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($rfc3339)
    {
        if (!is_string($rfc3339)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $rfc3339) {
            return;
        }

        try {
            $dateTime = new \DateTime($rfc3339);
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        if ($this->outputTimezone !== $dateTime->getTimezone()->getName()) {
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
