<?php

namespace Symfony\Component\Messenger\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

class HandlerFailedExceptionTest extends TestCase
{
    public function testThatStringErrorCodeConvertsToInteger()
    {
        $envelope = new Envelope(new \stdClass());
        $exception = new class() extends \RuntimeException {
            public function __construct()
            {
                $this->code = 'HY000';
                $this->message = 'test';
                // no to call parent constructor, it will fail with string error code
            }
        };

        $handlerException = new HandlerFailedException($envelope, [$exception]);
        $originalException = $handlerException->getNestedExceptions()[0];

        $this->assertIsInt($handlerException->getCode(), 'Exception codes must converts to int');
        $this->assertSame(0, $handlerException->getCode(), 'String code (HY000) converted to int must be 0');
        $this->assertIsString($originalException->getCode(), 'Original exception code still with original type (string)');
        $this->assertSame($exception->getCode(), $originalException->getCode(), 'Original exception code is not modified');
    }
}
