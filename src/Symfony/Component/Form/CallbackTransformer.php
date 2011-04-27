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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

class CallbackTransformer implements DataTransformerInterface
{
    private $transform;

    private $reverseTransform;

    public function __construct($transform, $reverseTransform)
    {
        if (!is_callable($transform)) {
            throw new UnexpectedTypeException($transform, 'function');
        }
        
        if (!is_callable($reverseTransform)) {
            throw new UnexpectedTypeException($reverseTransform, 'function');
        }

        $this->transform = $transform;
        $this->reverseTransform = $reverseTransform;
    }

    public function transform($data)
    {
        return call_user_func($this->transform, $data);
    }

    public function reverseTransform($data)
    {
        return call_user_func($this->reverseTransform, $data);
    }
}