<?php

namespace Symfony\Component\Validator\Exception;

class UnexpectedTypeException extends ValidatorException
{
    public function __construct($value, $expectedType)
    {
        parent::__construct(sprintf('Expected argument of type %s, %s given', $expectedType, gettype($value)));
    }
}