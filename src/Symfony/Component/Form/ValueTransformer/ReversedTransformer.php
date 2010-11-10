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
 * Reverses a transformer
 *
 * When the transform() method is called, the reversed transformer's
 * reverseTransform() method is called and vice versa.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class ReversedTransformer implements ValueTransformerInterface
{
    /**
     * The reversed transformer
     * @var ValueTransformerInterface
     */
    protected $reversedTransformer;

    /**
     * Reverses this transformer
     *
     * @param ValueTransformerInterface $innerTransformer
     */
    public function __construct(ValueTransformerInterface $reversedTransformer)
    {
        $this->reversedTransformer = $reversedTransformer;
    }

    /**
     * {@inheritDoc}
     */
    public function transform($value)
    {
        return $this->reversedTransformer->reverseTransform($value, null);
    }

    /**
     * {@inheritDoc}
     */
    public function reverseTransform($value, $originalValue)
    {
        return $this->reversedTransformer->transform($value);
    }

    /**
     * {@inheritDoc}
     */
    public function setLocale($locale)
    {
        $this->reversedTransformer->setLocale($locale);
    }
}
