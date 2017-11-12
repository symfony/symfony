<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ServiceUnavailableHttpExceptionTest extends HttpExceptionTest
{
    public function testHeadersDefaultRetryAfter(): void
    {
        $exception = new ServiceUnavailableHttpException(10);
        $this->assertSame(array('Retry-After' => 10), $exception->getHeaders());
    }

    public function testWithHeaderConstruct(): void
    {
        $headers = array(
            'Cache-Control' => 'public, s-maxage=1337',
        );

        $exception = new ServiceUnavailableHttpException(1337, null, null, null, $headers);

        $headers['Retry-After'] = 1337;

        $this->assertSame($headers, $exception->getHeaders());
    }

    /**
     * @dataProvider headerDataProvider
     */
    public function testHeadersSetter($headers): void
    {
        $exception = new ServiceUnavailableHttpException(10);
        $exception->setHeaders($headers);
        $this->assertSame($headers, $exception->getHeaders());
    }

    protected function createException()
    {
        return new ServiceUnavailableHttpException();
    }
}
