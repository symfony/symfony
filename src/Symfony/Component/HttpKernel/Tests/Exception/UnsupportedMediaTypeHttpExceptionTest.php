<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class UnsupportedMediaTypeHttpExceptionTest extends HttpExceptionTest
{
    /**
     * @dataProvider headerDataProvider
     */
    public function testHeadersSetter($headers)
    {
        $exception = new UnsupportedMediaTypeHttpException(10);
        $exception->setHeaders($headers);
        $this->assertSame($headers, $exception->getHeaders());
    }

    protected function createException($headers = array())
    {
        return new UnsupportedMediaTypeHttpException();
    }
}
