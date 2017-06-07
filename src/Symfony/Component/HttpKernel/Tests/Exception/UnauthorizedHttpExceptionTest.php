<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Test the UnauthorizedHttpException class.
 */
class UnauthorizedHttpExceptionTest extends HttpExceptionTest
{
    /**
     * Test that the default headers is set as expected.
     */
    public function testHeadersDefault()
    {
        $exception = new UnauthorizedHttpException('Challenge');
        $this->assertSame(array('WWW-Authenticate' => 'Challenge'), $exception->getHeaders());
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
        $exception = new UnauthorizedHttpException('Challenge');
        $exception->setHeaders($headers);
        $this->assertSame($headers, $exception->getHeaders());
    }
}
