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

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between a timezone identifier string and a DateTimeZone object.
 *
 * @author Roland Franssen <franssen.roland@gmai.com>
 */
class DateTimeZoneToStringTransformer implements DataTransformerInterface
{
    private $multiple;

    public function __construct(bool $multiple = false)
    {
        $this->multiple = $multiple;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($dateTimeZone)
    {
        if (null === $dateTimeZone) {
            return;
        }

        if ($this->multiple) {
            if (!is_array($dateTimeZone)) {
                throw new TransformationFailedException('Expected an array.');
            }

            return array_map(array(new self(), 'transform'), $dateTimeZone);
        }

        if (!$dateTimeZone instanceof \DateTimeZone) {
            throw new TransformationFailedException('Expected a \DateTimeZone.');
        }

        return $dateTimeZone->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return;
        }

        if ($this->multiple) {
            if (!is_array($value)) {
                throw new TransformationFailedException('Expected an array.');
            }

            return array_map(array(new self(), 'reverseTransform'), $value);
        }

        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        try {
            return new \DateTimeZone($value);
        } catch (\Exception $e) {
            throw new TransformationFailedException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
