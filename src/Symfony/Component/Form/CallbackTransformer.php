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

    public function __construct(\Closure $transform, \Closure $reverseTransform)
    {
        $this->transform = $transform;
        $this->reverseTransform = $reverseTransform;
    }

    public function transform($data)
    {
        return $this->transform->__invoke($data);
    }

    public function reverseTransform($data)
    {
        return $this->reverseTransform->__invoke($data);
    }
}