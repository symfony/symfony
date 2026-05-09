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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\MockObject\MockObject;
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
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ApplicationTest extends TestCase
{
    public function testBundleInterfaceImplementation()
    {
        $bundle = $this->createStub(BundleInterface::class);

        $kernel = $this->getKernel([$bundle], true);

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(['list']), new NullOutput());
    }

    public function testNotOverridingRegisterCommandsAvoidsDeprecation()
    {
        $bundle = new class extends Bundle {};

        $kernel = $this->getKernel([$bundle], true);

        $application = new Application($kernel);
        $application->doRun(new ArrayInput(['list']), new NullOutput());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testBundleCommandsAreRegistered()
    {
        $bundle = $this->createBundleMock([]);

        $kernel = $this->getKernel([$bundle], true);

        $application = new Application($kernel);

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/framework-bundle 8.1: Overriding the "Symfony\Component\HttpKernel\Bundle\Bundle::registerCommands()" method in "%s" is deprecated, use the "#[AsCommand]" attribute or the "console.command" service tag instead.', get_debug_type($bundle)));

        $application->doRun(new ArrayInput(['list']), new NullOutput());

        // Calling twice: registration should only be done once.
        $application->doRun(new ArrayInput(['list']), new NullOutput());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testBundleCommandsAreRetrievable()
    {
        $bundle = $this->createBundleMock([]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/framework-bundle 8.1: Overriding the "Symfony\Component\HttpKernel\Bundle\Bundle::registerCommands()" method in "%s" is deprecated, use the "#[AsCommand]" attribute or the "console.command" service tag instead.', get_debug_type($bundle)));

        $application->all();

        // Calling twice: registration should only be done once.
        $application->all();
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testBundleSingleCommandIsRetrievable()
    {
        $command = new Command('example');

        $bundle = $this->createBundleMock([$command]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/framework-bundle 8.1: Overriding the "Symfony\Component\HttpKernel\Bundle\Bundle::registerCommands()" method in "%s" is deprecated, use the "#[AsCommand]" attribute or the "console.command" service tag instead.', get_debug_type($bundle)));

        $this->assertSame($command, $application->get('example'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testBundleCommandCanBeFound()
    {
        $command = new Command('example');

        $bundle = $this->createBundleMock([$command]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/framework-bundle 8.1: Overriding the "Symfony\Component\HttpKernel\Bundle\Bundle::registerCommands()" method in "%s" is deprecated, use the "#[AsCommand]" attribute or the "console.command" service tag instead.', get_debug_type($bundle)));

        $this->assertSame($command, $application->find('example'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testBundleCommandCanBeFoundByAlias()
    {
        $command = new Command('example');
        $command->setAliases(['alias']);

        $bundle = $this->createBundleMock([$command]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/framework-bundle 8.1: Overriding the "Symfony\Component\HttpKernel\Bundle\Bundle::registerCommands()" method in "%s" is deprecated, use the "#[AsCommand]" attribute or the "console.command" service tag instead.', get_debug_type($bundle)));

        $this->assertSame($command, $application->find('alias'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testBundleCommandCanOverriddeAPreExistingCommandWithTheSameName()
    {
        $command = new Command('example');

        $bundle = $this->createBundleMock([$command]);

        $kernel = $this->getKernel([$bundle]);

        $application = new Application($kernel);
        $newCommand = new Command('example');
        $application->addCommand($newCommand);

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/framework-bundle 8.1: Overriding the "Symfony\Component\HttpKernel\Bundle\Bundle::registerCommands()" method in "%s" is deprecated, use the "#[AsCommand]" attribute or the "console.command" service tag instead.', get_debug_type($bundle)));

        $this->assertSame($newCommand, $application->get('example'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testRunOnlyWarnsOnUnregistrableCommand()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->register(ThrowingCommand::class, ThrowingCommand::class);
        $container->setParameter('console.command.ids', [ThrowingCommand::class => ThrowingCommand::class]);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel
            ->method('getBundles')
            ->willReturn([$this->createBundleMock(
                [(new Command('fine'))->setCode(static function (InputInterface $input, OutputInterface $output): int {
                    $output->write('fine');

                    return 0;
                })]
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
        $this->assertStringContainsString('Some commands could not be registered:', $output);
        $this->assertStringContainsString('throwing', $output);
        $this->assertStringContainsString('fine', $output);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testRegistrationErrorsAreDisplayedOnCommandNotFound()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel
            ->method('getBundles')
            ->willReturn([$this->createBundleMock(
                [(new Command(null))->setCode(static function (InputInterface $input, OutputInterface $output): int {
                    $output->write('fine');

                    return 0;
                })]
            )]);
        $kernel
            ->method('getContainer')
            ->willReturn($container);

        $application = new Application($kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'fine']);
        $output = $tester->getDisplay();

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Some commands could not be registered:', $output);
        $this->assertStringContainsString('Command "fine" is not defined.', $output);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testRunOnlyWarnsOnUnregistrableCommandAtTheEnd()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->register(ThrowingCommand::class, ThrowingCommand::class);
        $container->setParameter('console.command.ids', [ThrowingCommand::class => ThrowingCommand::class]);

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())->method('boot');
        $kernel
            ->method('getBundles')
            ->willReturn([$this->createBundleMock(
                [(new Command('fine'))->setCode(static function (InputInterface $input, OutputInterface $output): int {
                    $output->write('fine');

                    return 0;
                })]
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

        $this->assertStringContainsString(trim('[WARNING] Some commands could not be registered:'), trim($display[1]));
    }

    public function testSuggestingPackagesWithExactMatch()
    {
        $result = $this->createEventForSuggestingPackages('doctrine:fixtures', []);
        $this->assertMatchesRegularExpression('/You may be looking for a command provided by/', $result);
    }

    public function testSuggestingPackagesWithPartialMatchAndNoAlternatives()
    {
        $result = $this->createEventForSuggestingPackages('server', []);
        $this->assertMatchesRegularExpression('/You may be looking for a command provided by/', $result);
    }

    public function testSuggestingPackagesWithPartialMatchAndAlternatives()
    {
        $result = $this->createEventForSuggestingPackages('server', ['server:run']);
        $this->assertDoesNotMatchRegularExpression('/You may be looking for a command provided by/', $result);
    }

    private function createEventForSuggestingPackages(string $command, array $alternatives = []): string
    {
        $error = new CommandNotFoundException('', $alternatives);
        $event = new ConsoleErrorEvent(new ArrayInput([$command]), new NullOutput(), $error);
        $subscriber = new SuggestMissingPackageSubscriber();
        $subscriber->onConsoleError($event);

        return $event->getError()->getMessage();
    }

    /**
     * @param BundleInterface[] $bundles
     */
    private function getKernel(array $bundles, bool $useDispatcher = false): KernelInterface&MockObject
    {
        $container = new Container(new ParameterBag([
            'console.command.ids' => [],
            'console.lazy_command.ids' => [],
        ]));

        if ($useDispatcher) {
            $dispatcher = $this->createMock(EventDispatcherInterface::class);
            $dispatcher
                ->expects($this->atLeastOnce())
                ->method('dispatch')
            ;

            $container->set('event_dispatcher', $dispatcher);
        }

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects($this->once())->method('boot');
        $kernel
            ->method('getBundles')
            ->willReturn($bundles)
        ;
        $kernel
            ->method('getContainer')
            ->willReturn($container)
        ;

        return $kernel;
    }

    /**
     * @param array<callable|Command> $commands
     */
    private function createBundleMock(array $commands): Bundle&MockObject
    {
        $bundle = $this->createMock(Bundle::class);
        $bundle
            ->expects($this->once())
            ->method('registerCommands')
            ->willReturnCallback(static function (Application $application) use ($commands) {
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
