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

/**
 * Transforms between a boolean and a string.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class BooleanToStringTransformer extends BaseValueTransformer
{
    /**
     * Transforms a boolean into a string.
     *
     * @param  boolean $value   Boolean value.
     * @return string           String value.
     */
    public function transform($value)
    {
        if ($value === null) {
            return '';
        }

        if (!is_bool($value)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type boolean but got %s.', gettype($value)));
        }

        return true === $value ? '1' : '';
    }

    /**
     * Transforms a string into a boolean.
     *
     * @param  string $value  String value.
     * @return boolean        Boolean value.
     */
    public function reverseTransform($value, $originalValue)
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type string but got %s.', gettype($value)));
        }

        return $value !== '';
    }

}