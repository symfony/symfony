<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConsoleEventsTest extends TestCase
{
    protected function tearDown(): void
    {
        if (\function_exists('pcntl_signal')) {
            pcntl_async_signals(false);
            // We reset all signals to their default value to avoid side effects
            pcntl_signal(\SIGINT, \SIG_DFL);
            pcntl_signal(\SIGTERM, \SIG_DFL);
            pcntl_signal(\SIGUSR1, \SIG_DFL);
            pcntl_signal(\SIGUSR2, \SIG_DFL);
            pcntl_signal(\SIGALRM, \SIG_DFL);
        }
    }

    public function testEventAliases()
    {
        $container = new ContainerBuilder();
        $container->setParameter('event_dispatcher.event_aliases', ConsoleEvents::ALIASES);
        $container->addCompilerPass(new RegisterListenersPass());

        $container->register('event_dispatcher', EventDispatcher::class);
        $container->register('tracer', EventTraceSubscriber::class)
            ->setPublic(true)
            ->addTag('kernel.event_subscriber');
        $container->register('failing_command', FailingCommand::class);
        $container->register('application', Application::class)
            ->setPublic(true)
            ->addMethodCall('setAutoExit', [false])
            ->addMethodCall('setDispatcher', [new Reference('event_dispatcher')])
            ->addMethodCall('add', [new Reference('failing_command')])
        ;

        $container->compile();

        $tester = new ApplicationTester($container->get('application'));
        $tester->run(['fail']);

        $this->assertSame([ConsoleCommandEvent::class, ConsoleErrorEvent::class, ConsoleTerminateEvent::class], $container->get('tracer')->observedEvents);
    }
}

class EventTraceSubscriber implements EventSubscriberInterface
{
    public array $observedEvents = [];

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleCommandEvent::class => 'observe',
            ConsoleErrorEvent::class => 'observe',
            ConsoleTerminateEvent::class => 'observe',
        ];
    }

    public function observe(object $event): void
    {
        $this->observedEvents[] = get_debug_type($event);
    }
}

#[AsCommand(name: 'fail')]
class FailingCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        throw new \RuntimeException('I failed. Sorry.');
    }
}
