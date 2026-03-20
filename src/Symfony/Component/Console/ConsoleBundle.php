<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

use Psr\Clock\ClockInterface as PsrClockInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\AsTargetedValueResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\Console\DependencyInjection\ConsoleArgumentValueResolverPass;
use Symfony\Component\Console\DependencyInjection\RegisterCommandArgumentLocatorsPass;
use Symfony\Component\Console\DependencyInjection\RemoveEmptyCommandArgumentLocatorsPass;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Dotenv\Command\DebugCommand as DotenvDebugCommand;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as ContractsEventDispatcherInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class ConsoleBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        // must be registered before removing private services as some might be listeners/subscribers
        // but as late as possible to get resolved parameters
        $this->addCompilerPassIfExists($container, RegisterListenersPass::class, PassConfig::TYPE_BEFORE_REMOVING)?->setNoPreloadEvents([
            ConsoleEvents::COMMAND,
            ConsoleEvents::TERMINATE,
            ConsoleEvents::ERROR,
        ]);
        $container->addCompilerPass(new RegisterCommandArgumentLocatorsPass());
        $container->addCompilerPass(new RemoveEmptyCommandArgumentLocatorsPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new ConsoleArgumentValueResolverPass());
        $container->addCompilerPass(new AddConsoleCommandPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('Resources/config/services.php');
        $configurator->import('Resources/config/console.php');

        $container->registerAliasForArgument('parameter_bag', PsrContainerInterface::class);

        $container->registerForAutoconfiguration(Command::class)
            ->addTag('console.command')
            ->addTag('console.command.service_arguments');
        $container->registerForAutoconfiguration(ValueResolverInterface::class)
            ->addTag('console.argument_value_resolver');
        $container->registerForAutoconfiguration(ResourceCheckerInterface::class)
            ->addTag('config_cache.resource_checker');
        $container->registerForAutoconfiguration(EnvVarLoaderInterface::class)
            ->addTag('container.env_var_loader');
        $container->registerForAutoconfiguration(EnvVarProcessorInterface::class)
            ->addTag('container.env_var_processor');
        $container->registerForAutoconfiguration(ServiceLocator::class)
            ->addTag('container.service_locator');
        $container->registerForAutoconfiguration(ServiceSubscriberInterface::class)
            ->addTag('container.service_subscriber');
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)
            ->addTag('event_dispatcher.dispatcher');
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('kernel.event_subscriber');
        $container->registerForAutoconfiguration(LoggerAwareInterface::class)
            ->addMethodCall('setLogger', [new Reference('logger', $container::IGNORE_ON_INVALID_REFERENCE)]);

        $container->registerAttributeForAutoconfiguration(AsCommand::class, static function (ChildDefinition $definition, AsCommand $attribute, \ReflectionClass|\ReflectionMethod $reflector) {
            $tagAttributes = [
                'command' => $attribute->name,
                'description' => $attribute->description,
                'help' => $attribute->help ?? null,
            ];

            if ($reflector instanceof \ReflectionMethod) {
                $tagAttributes['method'] = $reflector->getName();
            }

            $definition->addTag('console.command', $tagAttributes);
            $definition->addTag('console.command.service_arguments');
        });
        $container->registerAttributeForAutoconfiguration(AsEventListener::class, static function (ChildDefinition $definition, AsEventListener $attribute, \ReflectionClass|\ReflectionMethod $reflector) {
            $tagAttributes = get_object_vars($attribute);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new LogicException(\sprintf('AsEventListener attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('kernel.event_listener', $tagAttributes);
        });
        $container->registerAttributeForAutoconfiguration(AsTargetedValueResolver::class, static function (ChildDefinition $definition, AsTargetedValueResolver $attribute): void {
            $definition->addTag('console.targeted_value_resolver', $attribute->name ? ['name' => $attribute->name] : []);
        });

        $container->registerForAutoconfiguration(CompilerPassInterface::class)
            ->addTag('container.excluded', ['source' => 'because it\'s a compiler pass']);
        $container->registerAttributeForAutoconfiguration(\Attribute::class, static function (ChildDefinition $definition) {
            $definition->addTag('container.excluded', ['source' => 'because it\'s a PHP attribute']);
        });

        $container->setParameter('container.behavior_describing_tags', [
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'kernel.event_subscriber',
            'kernel.event_listener',
            'kernel.reset',
        ]);

        if (!$container->getParameter('kernel.debug')) {
            // remove tagged iterator argument for resource checkers
            $container->getDefinition('config_cache_factory')->setArguments([]);
        }

        if (!class_exists(DotenvDebugCommand::class)) {
            $container->removeDefinition('console.command.dotenv_debug');
        }

        if (!interface_exists(EventDispatcherInterface::class)) {
            $container->removeDefinition('event_dispatcher');
            $container->removeDefinition('console.error_listener');
            $container->removeAlias(EventDispatcherInterface::class);
            $container->removeAlias(ContractsEventDispatcherInterface::class);
            $container->removeAlias(PsrEventDispatcherInterface::class);
        }

        if (!$container->willBeAvailable('symfony/clock', ClockInterface::class, ['symfony/console'])) {
            $container->removeDefinition('clock');
            $container->removeAlias(ClockInterface::class);
            $container->removeAlias(PsrClockInterface::class);
        }
    }

    /**
     * @template T of CompilerPassInterface
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function addCompilerPassIfExists(ContainerBuilder $container, string $class, string $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, int $priority = 0): CompilerPassInterface
    {
        $container->addResource(new ClassExistenceResource($class));
        $pass = null;

        if (class_exists($class)) {
            $container->addCompilerPass($pass = new $class(), $type, $priority);
        }

        return $pass;
    }
}
