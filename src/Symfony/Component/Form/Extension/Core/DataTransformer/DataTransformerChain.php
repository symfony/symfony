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

/**
 * Passes a value through multiple value transformers.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DataTransformerChain implements DataTransformerInterface
{
    /**
     * Uses the given value transformers to transform values.
     *
     * @param DataTransformerInterface[] $transformers
     */
    public function __construct(
        protected array $transformers,
    ) {
    }

    public function transform(mixed $value): mixed
    {
        foreach ($this->transformers as $transformer) {
            $value = $transformer->transform($value);
        }

        return $value;
    }

    public function reverseTransform(mixed $value): mixed
    {
        for ($i = \count($this->transformers) - 1; $i >= 0; --$i) {
            $value = $this->transformers[$i]->reverseTransform($value);
        }

        return $value;
    }

    /**
     * @return DataTransformerInterface[]
     */
    public function getTransformers(): array
    {
        return $this->transformers;
    }
}
