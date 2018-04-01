<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Extension\Core\DataTransformer;

use Symphony\Component\Form\DataTransformerInterface;
use Symphony\Component\Form\Exception\TransformationFailedException;

/**
 * Passes a value through multiple value transformers.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class DataTransformerChain implements DataTransformerInterface
{
    protected $transformers;

    /**
     * Uses the given value transformers to transform values.
     *
     * @param DataTransformerInterface[] $transformers
     */
    public function __construct(array $transformers)
    {
        $this->transformers = $transformers;
    }

    /**
     * Passes the value through the transform() method of all nested transformers.
     *
     * The transformers receive the value in the same order as they were passed
     * to the constructor. Each transformer receives the result of the previous
     * transformer as input. The output of the last transformer is returned
     * by this method.
     *
     * @param mixed $value The original value
     *
     * @return mixed The transformed value
     *
     * @throws TransformationFailedException
     */
    public function transform($value)
    {
        foreach ($this->transformers as $transformer) {
            $value = $transformer->transform($value);
        }

        return $value;
    }

    /**
     * Passes the value through the reverseTransform() method of all nested
     * transformers.
     *
     * The transformers receive the value in the reverse order as they were passed
     * to the constructor. Each transformer receives the result of the previous
     * transformer as input. The output of the last transformer is returned
     * by this method.
     *
     * @param mixed $value The transformed value
     *
     * @return mixed The reverse-transformed value
     *
     * @throws TransformationFailedException
     */
    public function reverseTransform($value)
    {
        for ($i = count($this->transformers) - 1; $i >= 0; --$i) {
            $value = $this->transformers[$i]->reverseTransform($value);
        }

        return $value;
    }

    /**
     * @return DataTransformerInterface[]
     */
    public function getTransformers()
    {
        return $this->transformers;
    }
}
