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
 *
 * @extends BaseDateTimeTransformer<string>
 */
class DateTimeToRfc3339Transformer extends BaseDateTimeTransformer
{
    public function transform(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if (!$value instanceof \DateTimeInterface) {
            throw new TransformationFailedException('Expected a \DateTimeInterface.');
        }

        if ($this->inputTimezone !== $this->outputTimezone) {
            $value = \DateTimeImmutable::createFromInterface($value);
            $value = $value->setTimezone(new \DateTimeZone($this->outputTimezone));
        }

        return preg_replace('/\+00:00$/', 'Z', $value->format('c'));
    }

    public function reverseTransform(mixed $value): ?\DateTime
    {
        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        if ('' === $value) {
            return null;
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|(?:(?:\+|-)\d{2}:\d{2}))$/', $value, $matches)) {
            throw new TransformationFailedException(\sprintf('The date "%s" is not a valid date.', $value));
        }

        try {
            $dateTime = new \DateTime($value);
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }

        if ($this->inputTimezone !== $dateTime->getTimezone()->getName()) {
            $dateTime->setTimezone(new \DateTimeZone($this->inputTimezone));
        }

        if (!checkdate($matches[2], $matches[3], $matches[1])) {
            throw new TransformationFailedException(\sprintf('The date "%s-%s-%s" is not a valid date.', $matches[1], $matches[2], $matches[3]));
        }

        return $dateTime;
    }
}
