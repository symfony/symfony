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

use Symfony\Component\Config\Resource\ClassExistenceResource;
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
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Dotenv\Command\DebugCommand as DotenvDebugCommand;
use Symfony\Component\EventDispatcher\DependencyInjection\AddEventAliasesPass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[RequiredBundle(ServicesBundle::class)]
class ConsoleBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $this->addCompilerPassIfExists($container, AddEventAliasesPass::class, arguments: [ConsoleEvents::ALIASES, [], [ConsoleEvents::COMMAND, ConsoleEvents::TERMINATE, ConsoleEvents::ERROR]]);
        $container->addCompilerPass(new RegisterCommandArgumentLocatorsPass());
        $container->addCompilerPass(new RemoveEmptyCommandArgumentLocatorsPass(), PassConfig::TYPE_BEFORE_REMOVING);
        $container->addCompilerPass(new ConsoleArgumentValueResolverPass());
        $container->addCompilerPass(new AddConsoleCommandPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $configurator->import('Resources/config/console.php');

        $container->registerForAutoconfiguration(Command::class)
            ->addTag('console.command')
            ->addTag('console.command.service_arguments');
        $container->registerForAutoconfiguration(ValueResolverInterface::class)
            ->addTag('console.argument_value_resolver');
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
        $container->registerAttributeForAutoconfiguration(AsTargetedValueResolver::class, static function (ChildDefinition $definition, AsTargetedValueResolver $attribute): void {
            $definition->addTag('console.targeted_value_resolver', $attribute->name ? ['name' => $attribute->name] : []);
        });

        if (!class_exists(DotenvDebugCommand::class)) {
            $container->removeDefinition('console.command.dotenv_debug');
        }

        if (!interface_exists(EventDispatcherInterface::class)) {
            $container->removeDefinition('console.error_listener');
        }
    }

    /**
     * @template T of CompilerPassInterface
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function addCompilerPassIfExists(ContainerBuilder $container, string $class, string $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, int $priority = 0, array $arguments = []): CompilerPassInterface
    {
        $container->addResource(new ClassExistenceResource($class));
        $pass = null;

        if (class_exists($class)) {
            $container->addCompilerPass($pass = new $class(...$arguments), $type, $priority);
        }

        return $pass;
    }
}
