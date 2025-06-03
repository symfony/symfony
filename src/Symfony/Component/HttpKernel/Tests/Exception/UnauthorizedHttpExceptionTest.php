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
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class UnauthorizedHttpExceptionTest extends HttpExceptionTest
{
    public function testHeadersDefault()
    {
        $exception = new UnauthorizedHttpException('Challenge');
        $this->assertSame(['WWW-Authenticate' => 'Challenge'], $exception->getHeaders());
    }

    public function testWithHeaderConstruct()
    {
        $headers = [
            'Cache-Control' => 'public, s-maxage=1200',
        ];

        $exception = new UnauthorizedHttpException('Challenge', '', null, 0, $headers);

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

    protected function createException(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new UnauthorizedHttpException('Challenge', $message, $previous, $code, $headers);
    }
}
