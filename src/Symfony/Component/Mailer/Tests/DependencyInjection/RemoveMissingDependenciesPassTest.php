<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;
use Symfony\Component\Mailer\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Mailer\EventListener\MessageLoggerListener;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testTheDataCollectorIsKeptWhenTheProfilerExists()
    {
        $container = $this->createContainer();
        $container->register('profiler');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->hasDefinition('mailer.data_collector'));
    }

    public function testTheDataCollectorIsDroppedWhenTheProfilerIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('mailer.data_collector'));
    }

    public function testTheMessageLoggerIsGatedOnTheProfilerState()
    {
        $container = $this->createContainer();
        $container->register('profiler');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertEquals(
            [new Reference('profiler.is_disabled_state_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE)],
            $container->getDefinition('mailer.message_logger_listener')->getArguments()
        );
    }

    public function testTheMessageLoggerKeepsCollectingInTestMode()
    {
        $container = $this->createContainer();
        $container->register('profiler');
        $container->register('test.client');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertSame([], $container->getDefinition('mailer.message_logger_listener')->getArguments());
    }

    public function testTheMessageLoggerIsDroppedWhenNothingConsumesIt()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('mailer.message_logger_listener'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('mailer.message_logger_listener', MessageLoggerListener::class);
        $container->register('mailer.data_collector', MessageDataCollector::class)
            ->addArgument(new Reference('mailer.message_logger_listener'));

        return $container;
    }
}
