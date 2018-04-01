<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpFoundation\File\Exception;

class UnexpectedTypeException extends FileException
{
    public function __construct($value, string $expectedType)
    {
        parent::__construct(sprintf('Expected argument of type %s, %s given', $expectedType, is_object($value) ? get_class($value) : gettype($value)));
    }
}
