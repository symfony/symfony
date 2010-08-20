<?php

namespace Symfony\Component\Validator\Exception;

class InvalidOptionsException extends ValidatorException
{
    private $options;

    public function __construct($message, array $options)
    {
        parent::__construct($message);

        $this->options = $options;
    }

    public function getOptions()
    {
        return $this->options;
    }
}