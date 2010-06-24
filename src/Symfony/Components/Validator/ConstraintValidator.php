<?php

namespace Symfony\Components\Validator;

abstract class ConstraintValidator implements ConstraintValidatorInterface
{
    protected $context;
    private $messageTemplate;
    private $messageParameters;

    public function initialize(ValidationContext $context)
    {
        $this->context = $context;
        $this->messageTemplate = '';
        $this->messageParameters = array();
    }

    public function getMessageTemplate()
    {
        return $this->messageTemplate;
    }

    public function getMessageParameters()
    {
        return $this->messageParameters;
    }

    protected function setMessage($template, array $parameters = array())
    {
        $this->messageTemplate = $template;
        $this->messageParameters = $parameters;
    }
}