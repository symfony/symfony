<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Decorator\CallableDecorator;
use Symfony\Component\Decorator\DependencyInjection\DecoratorsPass;
use Symfony\Component\Decorator\Resolver\DecoratorResolver;
use Symfony\Component\Decorator\Resolver\DecoratorResolverInterface;
use Symfony\Component\Decorator\Tests\Fixtures\Decorator\LoggingDecorator;
use Symfony\Component\Decorator\Tests\Fixtures\Handler\Message;
use Symfony\Component\Decorator\Tests\Fixtures\Handler\MessageHandler;
use Symfony\Component\Decorator\Tests\Fixtures\Logger\TestLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DecoratorPassTest extends TestCase
{
    public function testDefinition()
    {
        $container = $this->getDefinition();

        $pass = new DecoratorsPass();
        $pass->process($container);

        $argument = $container->getDefinition('decorator.callable_decorator')->getArgument(0);
        $resolver = $container->findDefinition((string) $argument);

        $this->assertSame(DecoratorResolver::class, $resolver->getClass());
        $this->assertSame([LoggingDecorator::class], array_keys($resolver->getArgument(0)));
    }

    public function testService()
    {
        $container = $this->getDefinition();
        $pass = new DecoratorsPass();
        $pass->process($container);

        $container->compile();

        $decorator = $container->get('decorator.callable_decorator');
        $this->assertInstanceOf(CallableDecorator::class, $decorator);

        $message = new Message();
        $result = $decorator->call(MessageHandler::handle2(...), $message);
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

        $container->register('decorator.callable_decorator', CallableDecorator::class)
            ->addArgument(new Reference(DecoratorResolverInterface::class))
            ->setPublic(true);

        $container->register(TestLogger::class)
            ->setPublic(true);

        $container->register(LoggingDecorator::class)
            ->addArgument(new Reference(TestLogger::class))
            ->addTag('decorator');

        return $container;
    }
}
