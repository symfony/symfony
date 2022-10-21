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
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class TooManyRequestsHttpExceptionTest extends HttpExceptionTest
{
    public function testHeadersDefaultRertyAfter()
    {
        $exception = new TooManyRequestsHttpException(10);
        $this->assertSame(['Retry-After' => 10], $exception->getHeaders());
    }

    public function testWithHeaderConstruct()
    {
        $headers = [
            'Cache-Control' => 'public, s-maxage=69',
        ];

        $exception = new TooManyRequestsHttpException(69, '', null, 0, $headers);

        $headers['Retry-After'] = 69;

        $this->assertSame($headers, $exception->getHeaders());
    }

    /**
     * @dataProvider headerDataProvider
     */
    public function testHeadersSetter($headers)
    {
        $exception = new TooManyRequestsHttpException(10);
        $exception->setHeaders($headers);
        $this->assertSame($headers, $exception->getHeaders());
    }

    protected function createException(string $message = '', \Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new TooManyRequestsHttpException(null, $message, $previous, $code, $headers);
    }
}
