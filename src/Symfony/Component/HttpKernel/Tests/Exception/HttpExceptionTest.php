<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class HttpExceptionTest extends \PHPUnit_Framework_TestCase
{
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

    public function testHeadersDefault()
    {
        $exception = $this->createException();
        $this->assertSame(array(), $exception->getHeaders());
    }

    /**
     * @dataProvider headerDataProvider
     */
    public function testHeadersConstructor($headers)
    {
        $exception = new HttpException(200, null, null, $headers);
        $this->assertSame($headers, $exception->getHeaders());
    }

    /**
     * @dataProvider headerDataProvider
     */
    public function testHeadersSetter($headers)
    {
        $exception = $this->createException();
        $exception->setHeaders($headers);
        $this->assertSame($headers, $exception->getHeaders());
    }

    protected function createException()
    {
        return new HttpException(200);
    }
}
