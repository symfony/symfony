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

class CallbackTransformer implements DataTransformerInterface
{
    private $transform;
    private $reverseTransform;

    /**
     * @param callable $transform        The forward transform callback
     * @param callable $reverseTransform The reverse transform callback
     */
    public function __construct(callable $transform, callable $reverseTransform)
    {
        $this->transform = $transform;
        $this->reverseTransform = $reverseTransform;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(mixed $data)
    {
        return ($this->transform)($data);
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform(mixed $data)
    {
        return ($this->reverseTransform)($data);
    }
}
