<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\CallableWrapper\CallableWrapper;
use Symfony\Component\CallableWrapper\Resolver\CallableWrapperResolver;
use Symfony\Component\CallableWrapper\Tests\Fixtures\CallableWrapper\Logging;
use Symfony\Component\CallableWrapper\Tests\Fixtures\CallableWrapper\LoggingCallableWrapper;
use Symfony\Component\CallableWrapper\Tests\Fixtures\Controller\CreateTaskController;
use Symfony\Component\CallableWrapper\Tests\Fixtures\Handler\InvokableMessageHandler;
use Symfony\Component\CallableWrapper\Tests\Fixtures\Handler\Message;
use Symfony\Component\CallableWrapper\Tests\Fixtures\Handler\MessageHandler;
use Symfony\Component\CallableWrapper\Tests\Fixtures\Logger\TestLogger;

class CallableWrapperTest extends TestCase
{
    private TestLogger $logger;
    private CallableWrapper $wrapper;

    protected function setUp(): void
    {
        $this->logger = new TestLogger();
        $this->wrapper = new CallableWrapper(new CallableWrapperResolver([
            LoggingCallableWrapper::class => fn () => new LoggingCallableWrapper($this->logger),
        ]));
    }

    public function testTopWrappedFunc()
    {
        $func = $this->wrapper->wrap(MessageHandler::handle2(...));
        $reflection = new \ReflectionFunction($func);

        $this->assertSame(LoggingCallableWrapper::class, $reflection->getClosureThis()::class);
    }

    public function testNestedWrappers()
    {
        $controller = new CreateTaskController();

        $result = $this->wrapper->call($controller);

        $expectedRecords = [
            [
                'level' => 'debug',
                'message' => 'Before calling func',
                'context' => ['args' => 0],
            ],
            [
                'level' => 'debug',
                'message' => 'After calling func',
                'context' => ['result' => '{"id":1,"description":"Take a break!"}'],
            ],
        ];

        $this->assertSame('{"id":1,"description":"Take a break!"}', $result);
        $this->assertSame($expectedRecords, $this->logger->records);
    }

    /**
     * @dataProvider getCallableProvider
     */
    public function testWrap(callable $callable, array $args, mixed $expectedResult, array $expectedRecords)
    {
        $result = $this->wrapper->call($callable, ...$args);

        $this->assertSame($expectedResult, $result);
        $this->assertSame($expectedRecords, $this->logger->records);
    }

    public function getCallableProvider(): iterable
    {
        yield 'non_decorated_function' => [
            strtoupper(...), ['bar'], 'BAR', [],
        ];

        #[Logging]
        function foo(string $bar): string
        {
            return $bar;
        }

        yield 'function' => [
            foo(...), ['bar'], 'bar', [
                [
                    'level' => 'debug',
                    'message' => 'Before calling func',
                    'context' => ['args' => 1],
                ],
                [
                    'level' => 'debug',
                    'message' => 'After calling func',
                    'context' => ['result' => 'bar'],
                ],
            ],
        ];

        $message = new Message();
        $handler = new MessageHandler();
        $invokableHandler = new InvokableMessageHandler();

        yield 'invokable_object' => [
            $invokableHandler, [$message], $message, [
                [
                    'level' => 'debug',
                    'message' => 'Before calling func',
                    'context' => ['args' => 1],
                ],
                [
                    'level' => 'debug',
                    'message' => 'After calling func',
                    'context' => ['result' => $message],
                ],
            ],
        ];

        yield 'invokable_method' => [
            $handler, [$message], $message, [
                [
                    'level' => 'debug',
                    'message' => 'Before calling func',
                    'context' => ['args' => 1],
                ],
                [
                    'level' => 'debug',
                    'message' => 'After calling func',
                    'context' => ['result' => $message],
                ],
            ],
        ];

        yield 'array' => [
            [$handler, 'handle1'], [$message], $message, [
                [
                    'level' => 'info',
                    'message' => 'Before calling func',
                    'context' => ['args' => 1],
                ],
                [
                    'level' => 'info',
                    'message' => 'After calling func',
                    'context' => ['result' => $message],
                ],
            ],
        ];

        yield 'array_static_method' => [
            [$handler::class, 'handle2'], [$message], $message, [
                [
                    'level' => 'debug',
                    'message' => 'Before calling func',
                    'context' => ['args' => 1],
                ],
                [
                    'level' => 'debug',
                    'message' => 'After calling func',
                    'context' => ['result' => $message],
                ],
            ],
        ];

        yield 'first_class_static_method' => [
            $handler::handle2(...), [$message], $message, [
                [
                    'level' => 'debug',
                    'message' => 'Before calling func',
                    'context' => ['args' => 1],
                ],
                [
                    'level' => 'debug',
                    'message' => 'After calling func',
                    'context' => ['result' => $message],
                ],
            ],
        ];
    }
}
