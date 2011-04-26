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
 * Reverses a transformer
 *
 * When the transform() method is called, the reversed transformer's
 * reverseTransform() method is called and vice versa.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ReversedTransformer implements DataTransformerInterface
{
    /**
     * The reversed transformer
     * @var DataTransformerInterface
     */
    protected $reversedTransformer;

    /**
     * Reverses this transformer
     *
     * @param DataTransformerInterface $reversedTransformer
     */
    public function __construct(DataTransformerInterface $reversedTransformer)
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
    public function reverseTransform($value)
    {
        return $this->reversedTransformer->transform($value);
    }
}
