<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Decorator\CallableDecorator;
use Symfony\Component\Decorator\Resolver\DecoratorResolver;
use Symfony\Component\Decorator\Tests\Fixtures\Controller\CreateTaskController;
use Symfony\Component\Decorator\Tests\Fixtures\Decorator\Logging;
use Symfony\Component\Decorator\Tests\Fixtures\Decorator\LoggingDecorator;
use Symfony\Component\Decorator\Tests\Fixtures\Handler\Message;
use Symfony\Component\Decorator\Tests\Fixtures\Handler\MessageHandler;
use Symfony\Component\Decorator\Tests\Fixtures\Logger\TestLogger;

class CallableDecoratorTest extends TestCase
{
    private TestLogger $logger;
    private CallableDecorator $decorator;

    protected function setUp(): void
    {
        $this->logger = new TestLogger();
        $this->decorator = new CallableDecorator(new DecoratorResolver([
            LoggingDecorator::class => fn () => new LoggingDecorator($this->logger),
        ]));
    }

    public function testTopDecoratedFunc()
    {
        $func = $this->decorator->decorate(MessageHandler::handle2(...));
        $reflection = new \ReflectionFunction($func);

        $this->assertSame(LoggingDecorator::class, $reflection->getClosureThis()::class);
    }

    public function testNestedDecorators()
    {
        $controller = new CreateTaskController();

        $result = $this->decorator->call($controller);

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
    public function testDecorate(callable $callable, array $args, mixed $expectedResult, array $expectedRecords)
    {
        $result = $this->decorator->call($callable, ...$args);

        $this->assertSame($expectedResult, $result);
        $this->assertSame($expectedRecords, $this->logger->records);
    }

    public function getCallableProvider(): iterable
    {
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

        yield 'invokable_object' => [
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
