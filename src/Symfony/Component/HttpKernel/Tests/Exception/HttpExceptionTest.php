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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HttpExceptionTest extends TestCase
{
    public static function headerDataProvider()
    {
        return [
            [['X-Test' => 'Test']],
            [['X-Test' => 1]],
            [
                [
                    ['X-Test' => 'Test'],
                    ['X-Test-2' => 'Test-2'],
                ],
            ],
        ];
    }

    public function testHeadersDefault()
    {
        $exception = $this->createException();
        $this->assertSame([], $exception->getHeaders());
    }

    /**
     * @dataProvider headerDataProvider
     */
    public function testHeadersConstructor($headers)
    {
        $exception = new HttpException(200, '', null, $headers);
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

    public function testThrowableIsAllowedForPrevious()
    {
        $previous = new class('Error of PHP 7+') extends \Error {
        };
        $exception = $this->createException('', $previous);
        $this->assertSame($previous, $exception->getPrevious());
    }

    protected function createException(string $message = '', \Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new HttpException(200, $message, $previous, $headers, $code);
    }
}
