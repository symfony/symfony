<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class ServiceUnavailableHttpExceptionTest extends HttpExceptionTest
{
    public function testHeadersDefaultRetryAfter()
    {
        $exception = new ServiceUnavailableHttpException(10);
        $this->assertSame(['Retry-After' => 10], $exception->getHeaders());
    }

    public function testWithHeaderConstruct()
    {
        $headers = [
            'Cache-Control' => 'public, s-maxage=1337',
        ];

        $exception = new ServiceUnavailableHttpException(1337, null, null, null, $headers);

        $headers['Retry-After'] = 1337;

        $this->assertSame($headers, $exception->getHeaders());
    }

    /**
     * @dataProvider headerDataProvider
     */
    public function testHeadersSetter($headers)
    {
        $exception = new ServiceUnavailableHttpException(10);
        $exception->setHeaders($headers);
        $this->assertSame($headers, $exception->getHeaders());
    }

    protected function createException(string $message = null, \Throwable $previous = null, ?int $code = 0, array $headers = [])
    {
        return new ServiceUnavailableHttpException(null, $message, $previous, $code, $headers);
    }
}
