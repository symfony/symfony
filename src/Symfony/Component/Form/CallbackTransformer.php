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
 * @template T
 * @template R
 *
 * @implements DataTransformerInterface<T, R>
 */
class CallbackTransformer implements DataTransformerInterface
{
    private $transform;
    private $reverseTransform;

    /**
     * @param callable(T):R $transform        The forward transform callback
     * @param callable(R):T $reverseTransform The reverse transform callback
     */
    public function __construct(callable $transform, callable $reverseTransform)
    {
        $this->transform = $transform;
        $this->reverseTransform = $reverseTransform;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data)
    {
        return ($this->transform)($data);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($data)
    {
        return ($this->reverseTransform)($data);
    }
}
