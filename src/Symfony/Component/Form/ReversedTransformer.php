<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

/**
 * Reverses a transformer.
 *
 * When the transform() method is called, the reversed transformer's
 * reverseTransform() method is called and vice versa.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ReversedTransformer implements DataTransformerInterface
{
    public function __construct(
        protected DataTransformerInterface $reversedTransformer,
    ) {
    }

    public function transform(mixed $value): mixed
    {
        return $this->reversedTransformer->reverseTransform($value);
    }

    public function reverseTransform(mixed $value): mixed
    {
        return $this->reversedTransformer->transform($value);
    }
}
