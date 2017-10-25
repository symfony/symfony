<?php

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class UnauthorizedHttpExceptionTest extends HttpExceptionTest
{
    public function testHeadersDefault()
    {
        $exception = new UnauthorizedHttpException('Challenge');
        $this->assertSame(array('WWW-Authenticate' => 'Challenge'), $exception->getHeaders());
    }

    public function testWithHeaderConstruct()
    {
        $headers = array(
            'Cache-Control' => 'public, s-maxage=1200',
        );

        $exception = new UnauthorizedHttpException('Challenge', null, null, null, $headers);

        $headers['WWW-Authenticate'] = 'Challenge';

        $this->assertSame($headers, $exception->getHeaders());
    }

    /**
     * @dataProvider headerDataProvider
     */
    public function testHeadersSetter($headers)
    {
        $exception = new UnauthorizedHttpException('Challenge');
        $exception->setHeaders($headers);
        $this->assertSame($headers, $exception->getHeaders());
    }
}
