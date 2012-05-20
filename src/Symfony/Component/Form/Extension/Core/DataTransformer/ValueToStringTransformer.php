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
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Transforms between a given value and a string.
 *
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
class ValueToStringTransformer implements DataTransformerInterface
{
    /**
     * Transforms a value into a string.
     *
     * @param mixed $value Mixed value.
     *
     * @return string String value.
     *
     * @throws UnexpectedTypeException if the given value is not a string or number
     */
    public function transform($value)
    {
        if (null === $value) {
            return '';
        }

        if (!is_string($value) && !is_numeric($value)) {
            throw new UnexpectedTypeException($value, 'string or number');
        }

        return $value;
    }

    /**
     * Transforms a value into a string.
     *
     * @param string $value String value.
     *
     * @return string String value.
     *
     * @throws UnexpectedTypeException if the given value is not a string
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return '';
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        return $value;
    }
}
