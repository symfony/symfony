<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\CallableWrapper\CallableWrapper;
use Symfony\Component\CallableWrapper\DependencyInjection\CallableWrappersPass;
use Symfony\Component\CallableWrapper\Resolver\CallableWrapperResolver;
use Symfony\Component\CallableWrapper\Resolver\CallableWrapperResolverInterface;
use Symfony\Component\CallableWrapper\Tests\Fixtures\CallableWrapper\LoggingCallableWrapper;
use Symfony\Component\CallableWrapper\Tests\Fixtures\Handler\Message;
use Symfony\Component\CallableWrapper\Tests\Fixtures\Handler\MessageHandler;
use Symfony\Component\CallableWrapper\Tests\Fixtures\Logger\TestLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CallableWrapperPassTest extends TestCase
{
    public function testDefinition()
    {
        $container = $this->getDefinition();

        $pass = new CallableWrappersPass();
        $pass->process($container);

        $argument = $container->getDefinition('callable_wrapper')->getArgument(0);
        $resolver = $container->findDefinition((string) $argument);

        $this->assertSame(CallableWrapperResolver::class, $resolver->getClass());
        $this->assertSame([LoggingCallableWrapper::class], array_keys($resolver->getArgument(0)));
    }

    public function testService()
    {
        $container = $this->getDefinition();
        $pass = new CallableWrappersPass();
        $pass->process($container);

        $container->compile();

        $wrapper = $container->get('callable_wrapper');
        $this->assertInstanceOf(CallableWrapper::class, $wrapper);

        $message = new Message();
        $result = $wrapper->call(MessageHandler::handle2(...), $message);
        $expectedRecords = [
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
        ];
        $this->assertSame($message, $result);
        $this->assertSame($expectedRecords, $container->get(TestLogger::class)->records);
    }

    private function getDefinition(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $container->register('callable_wrapper', CallableWrapper::class)
            ->addArgument(new Reference(CallableWrapperResolverInterface::class))
            ->setPublic(true);

        $container->register(TestLogger::class)
            ->setPublic(true);

        $container->register(LoggingCallableWrapper::class)
            ->addArgument(new Reference(TestLogger::class))
            ->addTag('callable_wrapper');

        return $container;
    }
}
