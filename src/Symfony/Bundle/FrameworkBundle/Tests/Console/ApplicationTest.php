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

use PHPUnit\Framework\Attributes\DataProvider;
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
use Symfony\Component\Console\Output\NullOutput;
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

    public function testEagerCommandRegistrationFailureIsRethrown()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);
        $container->register(ThrowingCommand::class, ThrowingCommand::class);
        $container->setParameter('console.command.ids', [ThrowingCommand::class => ThrowingCommand::class]);

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([]);
        $kernel->method('getContainer')->willReturn($container);

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Eagerly loading command "%s" failed', ThrowingCommand::class));

        (new ApplicationTester($application))->run(['command' => 'fine']);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testBundleCommandRegistrationFailureIsRethrown()
    {
        $container = new ContainerBuilder();
        $container->register('event_dispatcher', EventDispatcher::class);

        $bundle = new class extends Bundle {
            public function registerCommands(\Symfony\Component\Console\Application $application): void
            {
                throw new \LogicException('bundle boom');
            }
        };

        $kernel = $this->createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([$bundle]);
        $kernel->method('getContainer')->willReturn($container);

        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('"%s::registerCommands()" failed', $bundle::class));

        (new ApplicationTester($application))->run(['command' => 'fine']);
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

    #[DataProvider('provideCommandsSuggestingAPackage')]
    public function testSuggestingPackageForCommand(string $command, string $bundle, string $package)
    {
        $result = $this->createEventForSuggestingPackages($command);

        $this->assertStringContainsString(\sprintf('provided by the "%s" which is currently not installed. Try running "composer require %s".', $bundle, $package), $result);
    }

    public static function provideCommandsSuggestingAPackage(): iterable
    {
        yield 'api:openapi:export' => ['api:openapi:export', 'ApiPlatformBundle', 'api-platform/symfony'];
        yield 'bazinga:js-translation:dump' => ['bazinga:js-translation:dump', 'BazingaJsTranslationBundle', 'willdurand/js-translation-bundle'];
        yield 'debug:live-component' => ['debug:live-component', 'LiveComponentBundle', 'symfony/ux-live-component'];
        yield 'debug:twig-component' => ['debug:twig-component', 'TwigComponentBundle', 'symfony/ux-twig-component'];
        yield 'doctrine:fixtures:load' => ['doctrine:fixtures:load', 'DoctrineFixturesBundle', 'doctrine/doctrine-fixtures-bundle --dev'];
        yield 'doctrine:migrations:migrate' => ['doctrine:migrations:migrate', 'DoctrineMigrationsBundle', 'doctrine/doctrine-migrations-bundle'];
        yield 'doctrine:mongodb:schema:create' => ['doctrine:mongodb:schema:create', 'DoctrineMongoDBBundle', 'doctrine/mongodb-odm-bundle'];
        yield 'doctrine:schema:update' => ['doctrine:schema:update', 'Doctrine ORM', 'symfony/orm-pack'];
        yield 'hautelook:fixtures:load' => ['hautelook:fixtures:load', 'HautelookAliceBundle', 'hautelook/alice-bundle --dev'];
        yield 'league:oauth2-server:create-client' => ['league:oauth2-server:create-client', 'LeagueOAuth2ServerBundle', 'league/oauth2-server-bundle'];
        yield 'lexik:jwt:generate-keypair' => ['lexik:jwt:generate-keypair', 'LexikJWTAuthenticationBundle', 'lexik/jwt-authentication-bundle'];
        yield 'make:admin:dashboard' => ['make:admin:dashboard', 'EasyAdminBundle', 'easycorp/easyadmin-bundle'];
        yield 'make:controller' => ['make:controller', 'MakerBundle', 'symfony/maker-bundle --dev'];
        yield 'sass:build' => ['sass:build', 'SassBundle', 'symfonycasts/sass-bundle'];
        yield 'server:dump' => ['server:dump', 'Debug Bundle', 'symfony/debug-bundle --dev'];
        yield 'tailwind:build' => ['tailwind:build', 'TailwindBundle', 'symfonycasts/tailwind-bundle'];
        yield 'ux:icons:import' => ['ux:icons:import', 'UXIconsBundle', 'symfony/ux-icons'];
        yield 'ux:install' => ['ux:install', 'UXToolkitBundle', 'symfony/ux-toolkit'];
        yield 'ux:native:build-configs' => ['ux:native:build-configs', 'UXNativeBundle', 'symfony/ux-native'];
        yield 'ux:toolkit:create-kit' => ['ux:toolkit:create-kit', 'UXToolkitBundle', 'symfony/ux-toolkit'];
        yield 'ux:translator:warm-cache' => ['ux:translator:warm-cache', 'UxTranslatorBundle', 'symfony/ux-translator'];
    }

    public function testNotSuggestingPackageForUnknownNamespace()
    {
        $result = $this->createEventForSuggestingPackages('unknown:command');
        $this->assertStringNotContainsString('You may be looking for a command provided by', $result);
    }

    public function testNotSuggestingPackageWithoutDefaultAndPartialMatch()
    {
        $result = $this->createEventForSuggestingPackages('ux:unknown');
        $this->assertStringNotContainsString('You may be looking for a command provided by', $result);
    }

    public function testNotSuggestingPackageForACommandOfACoreNamespace()
    {
        $result = $this->createEventForSuggestingPackages('debug:route');
        $this->assertStringNotContainsString('You may be looking for a command provided by', $result);
    }

    public function testSuggestingPackagesWithExactMatchAndAlternatives()
    {
        // a partially installed namespace: DoctrineBundle is there, the fixtures bundle is not
        $result = $this->createEventForSuggestingPackages('doctrine:fixtures:load', ['doctrine', 'doctrine:database', 'doctrine:schema']);

        $this->assertStringContainsString('provided by the "DoctrineFixturesBundle"', $result);
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
