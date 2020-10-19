<?php

namespace Symfony\Component\ErrorHandler\Tests\Fixtures;

class StringErrorCodeException extends \Exception
{

    public function __construct(string $message, string $code) {
        parent::__construct($message);
        $this->code = $code;
    }

}
