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
    private \Closure $transform;
    private \Closure $reverseTransform;

    public function __construct(callable $transform, callable $reverseTransform)
    {
        $this->transform = $transform(...);
        $this->reverseTransform = $reverseTransform(...);
    }

    public function transform(mixed $value): mixed
    {
        return ($this->transform)($value);
    }

    public function reverseTransform(mixed $value): mixed
    {
        return ($this->reverseTransform)($value);
    }
}
