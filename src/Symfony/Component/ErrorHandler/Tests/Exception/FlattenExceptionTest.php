<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\ErrorHandler\Tests\Fixtures\StringErrorCodeException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\LengthRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class FlattenExceptionTest extends TestCase
{
    public function testStatusCode()
    {
        $flattened = FlattenException::createFromThrowable(new \RuntimeException(), 403);
        $this->assertEquals('403', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new \RuntimeException());
        $this->assertEquals('500', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new \DivisionByZeroError(), 403);
        $this->assertEquals('403', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new \DivisionByZeroError());
        $this->assertEquals('500', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new NotFoundHttpException());
        $this->assertEquals('404', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new UnauthorizedHttpException('Basic realm="My Realm"'));
        $this->assertEquals('401', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new BadRequestHttpException());
        $this->assertEquals('400', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new NotAcceptableHttpException());
        $this->assertEquals('406', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new ConflictHttpException());
        $this->assertEquals('409', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new MethodNotAllowedHttpException(['POST']));
        $this->assertEquals('405', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new AccessDeniedHttpException());
        $this->assertEquals('403', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new GoneHttpException());
        $this->assertEquals('410', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new LengthRequiredHttpException());
        $this->assertEquals('411', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new PreconditionFailedHttpException());
        $this->assertEquals('412', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new PreconditionRequiredHttpException());
        $this->assertEquals('428', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new ServiceUnavailableHttpException());
        $this->assertEquals('503', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new TooManyRequestsHttpException());
        $this->assertEquals('429', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new UnsupportedMediaTypeHttpException());
        $this->assertEquals('415', $flattened->getStatusCode());

        if (class_exists(SuspiciousOperationException::class)) {
            $flattened = FlattenException::createFromThrowable(new SuspiciousOperationException());
            $this->assertEquals('400', $flattened->getStatusCode());
        }
    }

    public function testHeadersForHttpException()
    {
        $flattened = FlattenException::createFromThrowable(new MethodNotAllowedHttpException(['POST']));
        $this->assertEquals(['Allow' => 'POST'], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new UnauthorizedHttpException('Basic realm="My Realm"'));
        $this->assertEquals(['WWW-Authenticate' => 'Basic realm="My Realm"'], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new ServiceUnavailableHttpException('Fri, 31 Dec 1999 23:59:59 GMT'));
        $this->assertEquals(['Retry-After' => 'Fri, 31 Dec 1999 23:59:59 GMT'], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new ServiceUnavailableHttpException(120));
        $this->assertEquals(['Retry-After' => 120], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new TooManyRequestsHttpException('Fri, 31 Dec 1999 23:59:59 GMT'));
        $this->assertEquals(['Retry-After' => 'Fri, 31 Dec 1999 23:59:59 GMT'], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new TooManyRequestsHttpException(120));
        $this->assertEquals(['Retry-After' => 120], $flattened->getHeaders());
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testFlattenHttpException(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $flattened2 = FlattenException::createFromThrowable($exception);

        $flattened->setPrevious($flattened2);

        $this->assertEquals($exception->getMessage(), $flattened->getMessage(), 'The message is copied from the original exception.');
        $this->assertEquals($exception->getCode(), $flattened->getCode(), 'The code is copied from the original exception.');
        $this->assertInstanceOf($flattened->getClass(), $exception, 'The class is set to the class of the original exception');
    }

    public function testThrowable()
    {
        $error = new \DivisionByZeroError('Ouch', 42);
        $flattened = FlattenException::createFromThrowable($error);

        $this->assertSame('Ouch', $flattened->getMessage(), 'The message is copied from the original error.');
        $this->assertSame(42, $flattened->getCode(), 'The code is copied from the original error.');
        $this->assertSame('DivisionByZeroError', $flattened->getClass(), 'The class is set to the class of the original error');
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testPrevious(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $flattened2 = FlattenException::createFromThrowable($exception);

        $flattened->setPrevious($flattened2);

        $this->assertSame($flattened2, $flattened->getPrevious());

        $this->assertSame([$flattened2], $flattened->getAllPrevious());
    }

    public function testPreviousError()
    {
        $exception = new \Exception('test', 123, new \ParseError('Oh noes!', 42));

        $flattened = FlattenException::createFromThrowable($exception)->getPrevious();

        $this->assertEquals('Oh noes!', $flattened->getMessage(), 'The message is copied from the original exception.');
        $this->assertEquals(42, $flattened->getCode(), 'The code is copied from the original exception.');
        $this->assertEquals('ParseError', $flattened->getClass(), 'The class is set to the class of the original exception');
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testLine(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $this->assertSame($exception->getLine(), $flattened->getLine());
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testFile(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $this->assertSame($exception->getFile(), $flattened->getFile());
    }

    /**
     * @dataProvider stringAndIntDataProvider
     */
    public function testCode(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $this->assertSame($exception->getCode(), $flattened->getCode());
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testToArray(\Throwable $exception, string $expectedClass)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $flattened->setTrace([], 'foo.php', 123);

        $this->assertEquals([
            [
                'message' => 'test',
                'class' => $expectedClass,
                'trace' => [[
                    'namespace' => '', 'short_class' => '', 'class' => '', 'type' => '', 'function' => '', 'file' => 'foo.php', 'line' => 123,
                    'args' => [],
                ]],
                'data' => null,
            ],
        ], $flattened->toArray());
    }

    public function testCreate()
    {
        $exception = new NotFoundHttpException(
            'test',
            new \RuntimeException('previous', 123)
        );

        $this->assertSame(
            FlattenException::createFromThrowable($exception)->toArray(),
            FlattenException::createFromThrowable($exception)->toArray()
        );
    }

    public static function flattenDataProvider(): array
    {
        return [
            [new \Exception('test', 123), 'Exception'],
            [new \Error('test', 123), 'Error'],
        ];
    }

    public static function stringAndIntDataProvider(): array
    {
        return [
            [new \Exception('test1', 123)],
            [new StringErrorCodeException('test2', '42S02')],
        ];
    }

    public function testAnonymousClass()
    {
        $flattened = FlattenException::createFromThrowable(new class extends \RuntimeException {
        });

        $this->assertSame('RuntimeException@anonymous', $flattened->getClass());

        $flattened->setClass((new class('Oops') extends NotFoundHttpException {
        })::class);

        $this->assertSame('Symfony\Component\HttpKernel\Exception\NotFoundHttpException@anonymous', $flattened->getClass());

        $flattened = FlattenException::createFromThrowable(new \Exception(\sprintf('Class "%s" blah.', (new class extends \RuntimeException {
        })::class)));

        $this->assertSame('Class "RuntimeException@anonymous" blah.', $flattened->getMessage());
    }

    public function testToStringEmptyMessage()
    {
        $exception = new \RuntimeException();

        $flattened = FlattenException::createFromThrowable($exception);

        $this->assertSame($exception->getTraceAsString(), $flattened->getTraceAsString());
        $this->assertSame($exception->__toString(), $flattened->getAsString());
    }

    public function testToString()
    {
        $test = fn ($a, $b, $c, $d) => new \RuntimeException('This is a test message');

        $exception = $test('foo123', 1, null, 1.5);

        $flattened = FlattenException::createFromThrowable($exception);

        $this->assertSame($exception->getTraceAsString(), $flattened->getTraceAsString());
        $this->assertSame($exception->__toString(), $flattened->getAsString());
    }

    public function testToStringParent()
    {
        $exception = new \LogicException('This is message 1');
        $exception = new \RuntimeException('This is messsage 2', 500, $exception);

        $flattened = FlattenException::createFromThrowable($exception);

        $this->assertSame($exception->getTraceAsString(), $flattened->getTraceAsString());
        $this->assertSame($exception->__toString(), $flattened->getAsString());
    }
}
