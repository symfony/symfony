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
 * Transforms between a Boolean and a string.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class BooleanToStringTransformer implements DataTransformerInterface
{
    /**
     * Transforms a Boolean into a string.
     *
     * @param  Boolean $value   Boolean value.
     *
     * @return string           String value.
     *
     * @throws UnexpectedTypeException if the given value is not a Boolean
     */
    public function transform($value)
    {
        if (null === $value) {
            return '';
        }

        if (!is_bool($value)) {
            throw new UnexpectedTypeException($value, 'Boolean');
        }

        return true === $value ? '1' : '';
    }

    /**
     * Transforms a string into a Boolean.
     *
     * @param  string $value  String value.
     *
     * @return Boolean        Boolean value.
     *
     * @throws UnexpectedTypeException if the given value is not a string
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return false;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        return '' !== $value;
    }

}
