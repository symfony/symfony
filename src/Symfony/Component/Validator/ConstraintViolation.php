<?php

namespace Symfony\Component\Validator;

class ConstraintViolation
{
    protected $messageTemplate;
    protected $messageParameters;
    protected $root;
    protected $propertyPath;
    protected $invalidValue;

    public function __construct($messageTemplate, array $messageParameters, $root, $propertyPath, $invalidValue)
    {
        $this->messageTemplate = $messageTemplate;
        $this->messageParameters = $messageParameters;
        $this->root = $root;
        $this->propertyPath = $propertyPath;
        $this->invalidValue = $invalidValue;
    }

    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    public function getMessage()
    {
        return str_replace(array_keys($sources), array_values($targets), $this->messageTemplate);
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