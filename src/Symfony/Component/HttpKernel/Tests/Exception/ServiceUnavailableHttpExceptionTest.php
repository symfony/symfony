<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
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

        $exception = new ServiceUnavailableHttpException(1337, '', null, 0, $headers);

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

    protected function createException(string $message = '', \Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new ServiceUnavailableHttpException(null, $message, $previous, $code, $headers);
    }
}
