<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Console;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\EventListener\SuggestMissingPackageSubscriber;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ApplicationTest extends TestCase
{
    public function testBundleInterfaceImplementation()
    {
        $bundle = self::createMock(BundleInterface::class);

        $kernel = $this->getKernel([$bundle], true);

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(['list']), new NullOutput());
    }

    public function testBundleCommandsAreRegistered()
    {
        $bundle = $this->createBundleMock([]);

        $kernel = $this->getKernel([$bundle], true);

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(['list']), new NullOutput());

        // Calling twice: registration should only be done once.
        $application->doRun(new ArrayInput(['list']), new NullOutput());
    }

    public function testBundleCommandsAreRetrievable()
    {
        $bundle = $this->createBundleMock([]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);
        $application->all();

        // Calling twice: registration should only be done once.
        $application->all();
    }

    public function testBundleSingleCommandIsRetrievable()
    {
        $command = new Command('example');

        $bundle = $this->createBundleMock([$command]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);

        self::assertSame($command, $application->get('example'));
    }

    public function testBundleCommandCanBeFound()
    {
        $command = new Command('example');

        $bundle = $this->createBundleMock([$command]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);

        self::assertSame($command, $application->find('example'));
    }

    public function testBundleCommandCanBeFoundByAlias()
    {
        $command = new Command('example');
        $command->setAliases(['alias']);

        $bundle = $this->createBundleMock([$command]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);

        self::assertSame($command, $application->find('alias'));
    }

    public function testBundleCommandCanOverriddeAPreExistingCommandWithTheSameName()
    {
        $command = new Command('example');

        $bundle = $this->createBundleMock([$command]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);
        $newCommand = new Command('example');
        $application->add($newCommand);

        self::assertSame($newCommand, $application->get('example'));
    }

    public function testRunOnlyWarnsOnUnregistrableCommand()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->register(ThrowingCommand::class, ThrowingCommand::class);
        $container->setParameter('console.command.ids', [ThrowingCommand::class => ThrowingCommand::class]);

        $kernel = self::createMock(KernelInterface::class);
        $kernel
            ->method('getBundles')
            ->willReturn([$this->createBundleMock(
                [(new Command('fine'))->setCode(function (InputInterface $input, OutputInterface $output) { $output->write('fine'); })]
            )]);
        $kernel
            ->method('getContainer')
            ->willReturn($container);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'fine']);
        $output = $tester->getDisplay();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Some commands could not be registered:', $output);
        self::assertStringContainsString('throwing', $output);
        self::assertStringContainsString('fine', $output);
    }

    public function testRegistrationErrorsAreDisplayedOnCommandNotFound()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);

        $kernel = self::createMock(KernelInterface::class);
        $kernel
            ->method('getBundles')
            ->willReturn([$this->createBundleMock(
                [(new Command(null))->setCode(function (InputInterface $input, OutputInterface $output) { $output->write('fine'); })]
            )]);
        $kernel
            ->method('getContainer')
            ->willReturn($container);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'fine']);
        $output = $tester->getDisplay();

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString('Some commands could not be registered:', $output);
        self::assertStringContainsString('Command "fine" is not defined.', $output);
    }

    public function testRunOnlyWarnsOnUnregistrableCommandAtTheEnd()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->register(ThrowingCommand::class, ThrowingCommand::class);
        $container->setParameter('console.command.ids', [ThrowingCommand::class => ThrowingCommand::class]);

        $kernel = self::createMock(KernelInterface::class);
        $kernel->expects(self::once())->method('boot');
        $kernel
            ->method('getBundles')
            ->willReturn([$this->createBundleMock(
                [(new Command('fine'))->setCode(function (InputInterface $input, OutputInterface $output) { $output->write('fine'); })]
            )]);
        $kernel
            ->method('getContainer')
            ->willReturn($container);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'list']);

        $tester->assertCommandIsSuccessful();
        $display = explode('List commands', $tester->getDisplay());

        self::assertStringContainsString(trim('[WARNING] Some commands could not be registered:'), trim($display[1]));
    }

    public function testSuggestingPackagesWithExactMatch()
    {
        $result = $this->createEventForSuggestingPackages('doctrine:fixtures', []);
        self::assertMatchesRegularExpression('/You may be looking for a command provided by/', $result);
    }

    public function testSuggestingPackagesWithPartialMatchAndNoAlternatives()
    {
        $result = $this->createEventForSuggestingPackages('server', []);
        self::assertMatchesRegularExpression('/You may be looking for a command provided by/', $result);
    }

    public function testSuggestingPackagesWithPartialMatchAndAlternatives()
    {
        $result = $this->createEventForSuggestingPackages('server', ['server:run']);
        self::assertDoesNotMatchRegularExpression('/You may be looking for a command provided by/', $result);
    }

    private function createEventForSuggestingPackages(string $command, array $alternatives = []): string
    {
        $error = new CommandNotFoundException('', $alternatives);
        $event = new ConsoleErrorEvent(new ArrayInput([$command]), new NullOutput(), $error);
        $subscriber = new SuggestMissingPackageSubscriber();
        $subscriber->onConsoleError($event);

        return $event->getError()->getMessage();
    }

    private function getKernel(array $bundles, $useDispatcher = false)
    {
        $container = self::createMock(ContainerInterface::class);

        if ($useDispatcher) {
            $dispatcher = self::createMock(EventDispatcherInterface::class);
            $dispatcher
                ->expects(self::atLeastOnce())
                ->method('dispatch')
            ;
            $container
                ->expects(self::atLeastOnce())
                ->method('get')
                ->with(self::equalTo('event_dispatcher'))
                ->willReturn($dispatcher);
        }

        $container
            ->expects(self::exactly(2))
            ->method('hasParameter')
            ->withConsecutive(['console.command.ids'], ['console.lazy_command.ids'])
            ->willReturnOnConsecutiveCalls(true, true)
        ;
        $container
            ->expects(self::exactly(2))
            ->method('getParameter')
            ->withConsecutive(['console.lazy_command.ids'], ['console.command.ids'])
            ->willReturnOnConsecutiveCalls([], [])
        ;

        $kernel = self::createMock(KernelInterface::class);
        $kernel->expects(self::once())->method('boot');
        $kernel
            ->expects(self::any())
            ->method('getBundles')
            ->willReturn($bundles)
        ;
        $kernel
            ->expects(self::any())
            ->method('getContainer')
            ->willReturn($container)
        ;

        return $kernel;
    }

    private function createBundleMock(array $commands)
    {
        $bundle = self::createMock(Bundle::class);
        $bundle
            ->expects(self::once())
            ->method('registerCommands')
            ->willReturnCallback(function (Application $application) use ($commands) {
                $application->addCommands($commands);
            })
        ;

        return $bundle;
    }
}

class ThrowingCommand extends Command
{
    public function __construct()
    {
        throw new \Exception('throwing');
    }
}
