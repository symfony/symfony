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
 * Transforms between a Boolean and a string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class BooleanToStringTransformer implements DataTransformerInterface
{
    /**
     * The value emitted upon transform if the input is true
     * @var string
     */
    private $trueValue;

    /**
     * Sets the value emitted upon transform if the input is true.
     *
     * @param string $trueValue
     */
    public function __construct($trueValue)
    {
        $this->trueValue = $trueValue;
    }

    /**
     * Transforms a Boolean into a string.
     *
     * @param bool $value Boolean value.
     *
     * @return string String value.
     *
     * @throws TransformationFailedException If the given value is not a Boolean.
     */
    public function transform($value)
    {
        if (null === $value) {
            return;
        }

        if (!is_bool($value)) {
            throw new TransformationFailedException('Expected a Boolean.');
        }

        return $value ? $this->trueValue : null;
    }

    /**
     * Transforms a string into a Boolean.
     *
     * @param string $value String value.
     *
     * @return bool Boolean value.
     *
     * @throws TransformationFailedException If the given value is not a string.
     */
    public function reverseTransform($value)
    {
        if (null === $value) {
            return false;
        }

        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        return true;
    }

}
