<?php

namespace Symfony\Components\Validator;

class ConstraintViolation
{
    protected $message;
    protected $root;
    protected $propertyPath;
    protected $invalidValue;

    public function __construct($message, $root, $propertyPath, $invalidValue)
    {
        $this->message = $message;
        $this->root = $root;
        $this->propertyPath = $propertyPath;
        $this->invalidValue = $invalidValue;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    public function getInvalidValue()
    {
        return $this->invalidValue;
    }
}