<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Test the ServiceUnavailableHttpException class.
 */
class ServiceUnavailableHttpExceptionTest extends HttpExceptionTest
{
    /**
     * Test that the default headers is an empty array.
     */
    public function testHeadersDefault()
    {
        $exception = new ServiceUnavailableHttpException();
        $this->assertSame(array(), $exception->getHeaders());
    }

    /**
     * Test that the default headers are set correctly
     * when the retryAfter parameter is set.
     */
    public function testHeadersDefaultRetryAfter()
    {
        $exception = new ServiceUnavailableHttpException(10);
        $this->assertSame(array('Retry-After' => 10), $exception->getHeaders());
    }

    /**
     * Test that setting the headers using the setter function
     * is working as expected.
     *
     * @param array $headers The headers to set.
     *
     * @dataProvider headerDataProvider
     */
    public function testHeadersSetter($headers)
    {
        $exception = new ServiceUnavailableHttpException(10);
        $exception->setHeaders($headers);
        $this->assertSame($headers, $exception->getHeaders());
    }
}
