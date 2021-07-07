<?php

namespace Symfony\Component\Messenger\Tests\Stamp;

class StringErrorCodeException extends \Exception
{
    public function __construct(string $message, string $code)
    {
        parent::__construct($message);
        $this->code = $code;
    }
}
