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
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between a Boolean and a string.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 *
 * @implements DataTransformerInterface<bool, string>
 */
class BooleanToStringTransformer implements DataTransformerInterface
{
    /**
     * @param string $trueValue The value emitted upon transform if the input is true
     */
    public function __construct(
        private string $trueValue,
        private array $falseValues = [null],
    ) {
        if (\in_array($this->trueValue, $this->falseValues, true)) {
            throw new InvalidArgumentException('The specified "true" value is contained in the false-values.');
        }
    }

    /**
     * Transforms a Boolean into a string.
     *
     * @param bool $value Boolean value
     *
     * @throws TransformationFailedException if the given value is not a Boolean
     */
    public function transform(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!\is_bool($value)) {
            throw new TransformationFailedException('Expected a Boolean.');
        }

        return $value ? $this->trueValue : null;
    }

    /**
     * Transforms a string into a Boolean.
     *
     * @param string $value String value
     *
     * @throws TransformationFailedException if the given value is not a string
     */
    public function reverseTransform(mixed $value): bool
    {
        if (\in_array($value, $this->falseValues, true)) {
            return false;
        }

        if (!\is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        return true;
    }
}
