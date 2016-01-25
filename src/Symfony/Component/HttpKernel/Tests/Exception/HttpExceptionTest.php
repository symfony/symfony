<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Test the HttpException class.
 */
class HttpExceptionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Provides header data for the tests.
     *
     * @return array
     */
    public function headerDataProvider()
    {
        return array(
            array(array('X-Test' => 'Test')),
            array(array('X-Test' => 1)),
            array(
                array(
                    array('X-Test' => 'Test'),
                    array('X-Test-2' => 'Test-2'),
                ),
            ),
        );
    }

    /**
     * Test that the default headers is an empty array.
     */
    public function testHeadersDefault()
    {
        $exception = new HttpException(200);
        $this->assertSame(array(), $exception->getHeaders());
    }

    /**
     * Test that setting the headers using the constructor parameter
     * is working as expected.
     *
     * @param array $headers The headers to set.
     *
     * @dataProvider headerDataProvider
     */
    public function testHeadersConstructor($headers)
    {
        $exception = new HttpException(200, null, null, $headers);
        $this->assertSame($headers, $exception->getHeaders());
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
        $exception = new HttpException(200);
        $exception->setHeaders($headers);
        $this->assertSame($headers, $exception->getHeaders());
    }
}
