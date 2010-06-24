<?php

namespace Symfony\Components\Validator\Exception;

class MissingOptionsException extends ValidatorException
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