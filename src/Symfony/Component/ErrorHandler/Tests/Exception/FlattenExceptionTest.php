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
        self::assertEquals('403', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new \RuntimeException());
        self::assertEquals('500', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new \DivisionByZeroError(), 403);
        self::assertEquals('403', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new \DivisionByZeroError());
        self::assertEquals('500', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new NotFoundHttpException());
        self::assertEquals('404', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new UnauthorizedHttpException('Basic realm="My Realm"'));
        self::assertEquals('401', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new BadRequestHttpException());
        self::assertEquals('400', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new NotAcceptableHttpException());
        self::assertEquals('406', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new ConflictHttpException());
        self::assertEquals('409', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new MethodNotAllowedHttpException(['POST']));
        self::assertEquals('405', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new AccessDeniedHttpException());
        self::assertEquals('403', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new GoneHttpException());
        self::assertEquals('410', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new LengthRequiredHttpException());
        self::assertEquals('411', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new PreconditionFailedHttpException());
        self::assertEquals('412', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new PreconditionRequiredHttpException());
        self::assertEquals('428', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new ServiceUnavailableHttpException());
        self::assertEquals('503', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new TooManyRequestsHttpException());
        self::assertEquals('429', $flattened->getStatusCode());

        $flattened = FlattenException::createFromThrowable(new UnsupportedMediaTypeHttpException());
        self::assertEquals('415', $flattened->getStatusCode());

        if (class_exists(SuspiciousOperationException::class)) {
            $flattened = FlattenException::createFromThrowable(new SuspiciousOperationException());
            self::assertEquals('400', $flattened->getStatusCode());
        }
    }

    public function testHeadersForHttpException()
    {
        $flattened = FlattenException::createFromThrowable(new MethodNotAllowedHttpException(['POST']));
        self::assertEquals(['Allow' => 'POST'], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new UnauthorizedHttpException('Basic realm="My Realm"'));
        self::assertEquals(['WWW-Authenticate' => 'Basic realm="My Realm"'], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new ServiceUnavailableHttpException('Fri, 31 Dec 1999 23:59:59 GMT'));
        self::assertEquals(['Retry-After' => 'Fri, 31 Dec 1999 23:59:59 GMT'], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new ServiceUnavailableHttpException(120));
        self::assertEquals(['Retry-After' => 120], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new TooManyRequestsHttpException('Fri, 31 Dec 1999 23:59:59 GMT'));
        self::assertEquals(['Retry-After' => 'Fri, 31 Dec 1999 23:59:59 GMT'], $flattened->getHeaders());

        $flattened = FlattenException::createFromThrowable(new TooManyRequestsHttpException(120));
        self::assertEquals(['Retry-After' => 120], $flattened->getHeaders());
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testFlattenHttpException(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $flattened2 = FlattenException::createFromThrowable($exception);

        $flattened->setPrevious($flattened2);

        self::assertEquals($exception->getMessage(), $flattened->getMessage(), 'The message is copied from the original exception.');
        self::assertEquals($exception->getCode(), $flattened->getCode(), 'The code is copied from the original exception.');
        self::assertInstanceOf($flattened->getClass(), $exception, 'The class is set to the class of the original exception');
    }

    public function testThrowable()
    {
        $error = new \DivisionByZeroError('Ouch', 42);
        $flattened = FlattenException::createFromThrowable($error);

        self::assertSame('Ouch', $flattened->getMessage(), 'The message is copied from the original error.');
        self::assertSame(42, $flattened->getCode(), 'The code is copied from the original error.');
        self::assertSame('DivisionByZeroError', $flattened->getClass(), 'The class is set to the class of the original error');
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testPrevious(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $flattened2 = FlattenException::createFromThrowable($exception);

        $flattened->setPrevious($flattened2);

        self::assertSame($flattened2, $flattened->getPrevious());

        self::assertSame([$flattened2], $flattened->getAllPrevious());
    }

    public function testPreviousError()
    {
        $exception = new \Exception('test', 123, new \ParseError('Oh noes!', 42));

        $flattened = FlattenException::createFromThrowable($exception)->getPrevious();

        self::assertEquals('Oh noes!', $flattened->getMessage(), 'The message is copied from the original exception.');
        self::assertEquals(42, $flattened->getCode(), 'The code is copied from the original exception.');
        self::assertEquals('ParseError', $flattened->getClass(), 'The class is set to the class of the original exception');
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testLine(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        self::assertSame($exception->getLine(), $flattened->getLine());
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testFile(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        self::assertSame($exception->getFile(), $flattened->getFile());
    }

    /**
     * @dataProvider stringAndIntDataProvider
     */
    public function testCode(\Throwable $exception)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        self::assertSame($exception->getCode(), $flattened->getCode());
    }

    /**
     * @dataProvider flattenDataProvider
     */
    public function testToArray(\Throwable $exception, string $expectedClass)
    {
        $flattened = FlattenException::createFromThrowable($exception);
        $flattened->setTrace([], 'foo.php', 123);

        self::assertEquals([
            [
                'message' => 'test',
                'class' => $expectedClass,
                'trace' => [[
                    'namespace' => '', 'short_class' => '', 'class' => '', 'type' => '', 'function' => '', 'file' => 'foo.php', 'line' => 123,
                    'args' => [],
                ]],
            ],
        ], $flattened->toArray());
    }

    public function testCreate()
    {
        $exception = new NotFoundHttpException(
            'test',
            new \RuntimeException('previous', 123)
        );

        self::assertSame(FlattenException::createFromThrowable($exception)->toArray(), FlattenException::createFromThrowable($exception)->toArray());
    }

    public function flattenDataProvider(): array
    {
        return [
            [new \Exception('test', 123), 'Exception'],
            [new \Error('test', 123), 'Error'],
        ];
    }

    public function stringAndIntDataProvider(): array
    {
        return [
            [new \Exception('test1', 123)],
            [new StringErrorCodeException('test2', '42S02')],
        ];
    }

    public function testArguments()
    {
        if (\PHP_VERSION_ID >= 70400) {
            self::markTestSkipped('PHP 7.4 removes arguments from exception traces.');
        }

        $dh = opendir(__DIR__);
        $fh = tmpfile();

        $incomplete = unserialize('O:14:"BogusTestClass":0:{}');

        $exception = $this->createException([
            (object) ['foo' => 1],
            new NotFoundHttpException(),
            $incomplete,
            $dh,
            $fh,
            function () {},
            [1, 2],
            ['foo' => 123],
            null,
            true,
            false,
            0,
            0.0,
            '0',
            '',
            \INF,
            \NAN,
        ]);

        $flattened = FlattenException::createFromThrowable($exception);
        $trace = $flattened->getTrace();
        $args = $trace[1]['args'];
        $array = $args[0][1];

        closedir($dh);
        fclose($fh);

        $i = 0;
        self::assertSame(['object', 'stdClass'], $array[$i++]);
        self::assertSame(['object', 'Symfony\Component\HttpKernel\Exception\NotFoundHttpException'], $array[$i++]);
        self::assertSame(['incomplete-object', 'BogusTestClass'], $array[$i++]);
        self::assertSame(['resource', 'stream'], $array[$i++]);
        self::assertSame(['resource', 'stream'], $array[$i++]);

        $args = $array[$i++];
        self::assertSame('object', $args[0]);
        self::assertTrue('Closure' === $args[1] || is_subclass_of($args[1], \Closure::class), 'Expect object class name to be Closure or a subclass of Closure.');

        self::assertSame(['array', [['integer', 1], ['integer', 2]]], $array[$i++]);
        self::assertSame(['array', ['foo' => ['integer', 123]]], $array[$i++]);
        self::assertSame(['null', null], $array[$i++]);
        self::assertSame(['boolean', true], $array[$i++]);
        self::assertSame(['boolean', false], $array[$i++]);
        self::assertSame(['integer', 0], $array[$i++]);
        self::assertSame(['float', 0.0], $array[$i++]);
        self::assertSame(['string', '0'], $array[$i++]);
        self::assertSame(['string', ''], $array[$i++]);
        self::assertSame(['float', \INF], $array[$i++]);

        // assertEquals() does not like NAN values.
        self::assertEquals('float', $array[$i][0]);
        self::assertNan($array[$i][1]);
    }

    public function testRecursionInArguments()
    {
        if (\PHP_VERSION_ID >= 70400) {
            self::markTestSkipped('PHP 7.4 removes arguments from exception traces.');
        }

        $a = null;
        $a = ['foo', [2, &$a]];
        $exception = $this->createException($a);

        $flattened = FlattenException::createFromThrowable($exception);
        $trace = $flattened->getTrace();
        self::assertStringContainsString('*DEEP NESTED ARRAY*', serialize($trace));
    }

    public function testTooBigArray()
    {
        if (\PHP_VERSION_ID >= 70400) {
            self::markTestSkipped('PHP 7.4 removes arguments from exception traces.');
        }

        $a = [];
        for ($i = 0; $i < 20; ++$i) {
            for ($j = 0; $j < 50; ++$j) {
                for ($k = 0; $k < 10; ++$k) {
                    $a[$i][$j][$k] = 'value';
                }
            }
        }
        $a[20] = 'value';
        $a[21] = 'value1';
        $exception = $this->createException($a);

        $flattened = FlattenException::createFromThrowable($exception);
        $trace = $flattened->getTrace();

        self::assertSame(['array', ['array', '*SKIPPED over 10000 entries*']], $trace[1]['args'][0]);

        $serializeTrace = serialize($trace);

        self::assertStringContainsString('*SKIPPED over 10000 entries*', $serializeTrace);
        self::assertStringNotContainsString('*value1*', $serializeTrace);
    }

    public function testAnonymousClass()
    {
        $flattened = FlattenException::createFromThrowable(new class() extends \RuntimeException {
        });

        self::assertSame('RuntimeException@anonymous', $flattened->getClass());

        $flattened->setClass(\get_class(new class('Oops') extends NotFoundHttpException {
        }));

        self::assertSame('Symfony\Component\HttpKernel\Exception\NotFoundHttpException@anonymous', $flattened->getClass());

        $flattened = FlattenException::createFromThrowable(new \Exception(sprintf('Class "%s" blah.', \get_class(new class() extends \RuntimeException {
        }))));

        self::assertSame('Class "RuntimeException@anonymous" blah.', $flattened->getMessage());
    }

    public function testToStringEmptyMessage()
    {
        $exception = new \RuntimeException();

        $flattened = FlattenException::createFromThrowable($exception);

        self::assertSame($exception->getTraceAsString(), $flattened->getTraceAsString());
        self::assertSame($exception->__toString(), $flattened->getAsString());
    }

    public function testToString()
    {
        $test = function ($a, $b, $c, $d) {
            return new \RuntimeException('This is a test message');
        };

        $exception = $test('foo123', 1, null, 1.5);

        $flattened = FlattenException::createFromThrowable($exception);

        self::assertSame($exception->getTraceAsString(), $flattened->getTraceAsString());
        self::assertSame($exception->__toString(), $flattened->getAsString());
    }

    public function testToStringParent()
    {
        $exception = new \LogicException('This is message 1');
        $exception = new \RuntimeException('This is messsage 2', 500, $exception);

        $flattened = FlattenException::createFromThrowable($exception);

        self::assertSame($exception->getTraceAsString(), $flattened->getTraceAsString());
        self::assertSame($exception->__toString(), $flattened->getAsString());
    }

    private function createException($foo): \Exception
    {
        return new \Exception();
    }
}
