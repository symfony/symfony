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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ContentTooLargeHttpException;
use Symfony\Component\HttpKernel\Exception\ExpectationFailedHttpException;
use Symfony\Component\HttpKernel\Exception\FailedDependencyHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\LockedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\NotImplementedHttpException;
use Symfony\Component\HttpKernel\Exception\PaymentRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\RangeNotSatisfiableHttpException;
use Symfony\Component\HttpKernel\Exception\RequestTimeoutHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooEarlyHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnavailableForLegalReasonsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

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

    #[DataProvider('headerDataProvider')]
    public function testHeadersConstructor($headers)
    {
        $exception = new HttpException(200, '', null, $headers);
        $this->assertSame($headers, $exception->getHeaders());
    }

    #[DataProvider('headerDataProvider')]
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

    #[DataProvider('provideStatusCode')]
    public function testFromStatusCode(int $statusCode, string $class)
    {
        $exception = HttpException::fromStatusCode($statusCode);
        $this->assertSame($class, $exception::class);
        $this->assertSame($statusCode, $exception->getStatusCode());
    }

    public static function provideStatusCode()
    {
        return [
            [400, BadRequestHttpException::class],
            [401, HttpException::class],
            [402, PaymentRequiredHttpException::class],
            [403, AccessDeniedHttpException::class],
            [404, NotFoundHttpException::class],
            [406, NotAcceptableHttpException::class],
            [408, RequestTimeoutHttpException::class],
            [409, ConflictHttpException::class],
            [410, GoneHttpException::class],
            [411, LengthRequiredHttpException::class],
            [412, PreconditionFailedHttpException::class],
            [413, ContentTooLargeHttpException::class],
            [415, UnsupportedMediaTypeHttpException::class],
            [416, RangeNotSatisfiableHttpException::class],
            [417, ExpectationFailedHttpException::class],
            [418, HttpException::class],
            [422, UnprocessableEntityHttpException::class],
            [423, LockedHttpException::class],
            [424, FailedDependencyHttpException::class],
            [425, TooEarlyHttpException::class],
            [428, PreconditionRequiredHttpException::class],
            [429, TooManyRequestsHttpException::class],
            [451, UnavailableForLegalReasonsHttpException::class],
            [501, NotImplementedHttpException::class],
            [503, ServiceUnavailableHttpException::class],
        ];
    }

    protected function createException(string $message = '', ?\Throwable $previous = null, int $code = 0, array $headers = []): HttpException
    {
        return new HttpException(200, $message, $previous, $headers, $code);
    }
}
