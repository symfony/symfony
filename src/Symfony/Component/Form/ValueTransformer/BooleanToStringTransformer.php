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
 * Transforms between a boolean and a string.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class BooleanToStringTransformer extends Configurable implements ValueTransformerInterface
{
    /**
     * Transforms a Boolean into a string.
     *
     * @param  Boolean $value   Boolean value.
     * @return string           String value.
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
     * @return Boolean        Boolean value.
     */
    public function reverseTransform($value)
    {
        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        return '' !== $value;
    }

}