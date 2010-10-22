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
 * Passes a value through multiple value transformers
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class ValueTransformerChain implements ValueTransformerInterface
{
    /**
     * The value transformers
     * @var array
     */
    protected $transformers;

    /**
     * Uses the given value transformers to transform values
     *
     * @param array $transformers
     */
    public function __construct(array $transformers)
    {
        $this->transformers = $transformers;
    }

    /**
     * Passes the value through the transform() method of all nested transformers
     *
     * The transformers receive the value in the same order as they were passed
     * to the constructor. Each transformer receives the result of the previous
     * transformer as input. The output of the last transformer is returned
     * by this method.
     *
     * @param  mixed $value  The original value
     * @return mixed         The transformed value
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
     * transformers
     *
     * The transformers receive the value in the reverse order as they were passed
     * to the constructor. Each transformer receives the result of the previous
     * transformer as input. The output of the last transformer is returned
     * by this method.
     *
     * @param  mixed $value  The transformed value
     * @return mixed         The reverse-transformed value
     */
    public function reverseTransform($value, $originalValue)
    {
        for ($i = count($this->transformers) - 1; $i >= 0; --$i) {
            $value = $this->transformers[$i]->reverseTransform($value, $originalValue);
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function setLocale($locale)
    {
        foreach ($this->transformers as $transformer) {
            $transformer->setLocale($locale);
        }
    }
}
