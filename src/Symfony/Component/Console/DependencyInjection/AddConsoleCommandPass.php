<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\DependencyInjection;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LazyCommand;
use Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * Registers console commands.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class AddConsoleCommandPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $commandServices = [];
        $lazyCommandMap = [];
        $lazyCommandRefs = [];
        $serviceIds = [];

        foreach ($container->findTaggedServiceIds('console.command', true) as $id => $tags) {
            foreach ($tags as $tag) {
                $commandServices[$id][$tag['method'] ?? '__invoke'][] = $tag;
            }
        }

        foreach ($commandServices as $id => $commands) {
            $definition = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!$r = $container->getReflectionClass($class)) {
                throw new InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }

            // the class-level name prefixes the names declared on methods
            $prefix = $commands['__invoke'][0]['command'] ?? $this->getCommandAttribute($r)?->name;
            if (null !== $prefix) {
                $hidden = str_starts_with($prefix, '|') ? '|' : '';
                $prefix = explode('|', ltrim($prefix, '|'))[0];
                $prefix = '' === $prefix ? null : $hidden.$prefix;
            }

            foreach ($commands as $method => $tags) {
                if ('__invoke' === $method && 1 < \count($commands) && !$r->hasMethod('__invoke') && !$r->isSubclassOf(Command::class)) {
                    // the class-level attribute only names the prefix of the method-level commands
                    continue;
                }

                $this->registerCommand($container, $r, $id, $class, $tags, $definition, $serviceIds, $lazyCommandMap, $lazyCommandRefs, '__invoke' === $method ? null : $prefix);
            }
        }

        $container
            ->register('console.command_loader', ContainerCommandLoader::class)
            ->setPublic(true)
            ->addTag('container.no_preload')
            ->setArguments([ServiceLocatorTagPass::register($container, $lazyCommandRefs), $lazyCommandMap]);

        $container->setParameter('console.command.ids', $serviceIds);
    }

    private function registerCommand(ContainerBuilder $container, \ReflectionClass $reflection, string $id, string $class, array $tags, Definition $definition, array &$serviceIds, array &$lazyCommandMap, array &$lazyCommandRefs, ?string $prefix = null): void
    {
        if (!$reflection->isSubclassOf(Command::class)) {
            $method = $tags[0]['method'] ?? '__invoke';

            if (!$reflection->hasMethod($method)) {
                throw new InvalidArgumentException(\sprintf('The service "%s" tagged "%s" must either be a subclass of "%s", have an "%s()" method%s.', $id, 'console.command', Command::class, $method, '__invoke' === $method ? ', or declare method-level commands' : ''));
            }

            $reflection = $reflection->getMethod($method);

            if (!$reflection->isPublic() || $reflection->isStatic()) {
                throw new InvalidArgumentException(\sprintf('The method "%s::%s()" must be public and non-static to be used as a console command.', $class, $method));
            }

            if ('__invoke' === $method) {
                $callableRef = new Reference($id);
                $id .= '.command';
            } else {
                $callableRef = [new Reference($id), $method];
                $id .= '.'.$method.'.command';
            }
            $class = Command::class;

            $closureDefinition = new Definition(\Closure::class)
                ->setFactory([\Closure::class, 'fromCallable'])
                ->setArguments([$callableRef]);

            $definition = $container->register($id, $class)
                ->addMethodCall('setCode', [$closureDefinition]);
        } elseif (isset($tags[0]['method'])) {
            throw new InvalidArgumentException(\sprintf('The service "%s" tagged "console.command" cannot define a method command when it is a subclass of "%s".', $id, Command::class));
        }

        $definition->addTag('container.no_preload');

        $attribute = $this->getCommandAttribute($reflection);
        $aliases = $tags[0]['command'] ?? $attribute?->name ?? '';

        if (null !== $prefix && '' !== $aliases) {
            $names = explode('|', $aliases);
            if (str_starts_with($prefix, '|')) {
                // the method commands of a hidden class-level command are hidden too
                $prefix = substr($prefix, 1);
                if ('' !== $names[0]) {
                    array_unshift($names, '');
                }
            }
            $aliases = implode('|', array_map(static function (string $name) use ($prefix, $reflection) {
                if ('' === $name) {
                    return $name;
                }
                if (str_starts_with($name, $prefix.':')) {
                    throw new InvalidArgumentException(\sprintf('The name "%s" of the command "%s::%s()" repeats the class-level name "%s": method-level names are relative to it, use "%s" instead.', $name, $reflection->class, $reflection->name, $prefix, substr($name, \strlen($prefix) + 1)));
                }

                return $prefix.':'.$name;
            }, $names));
        }

        $aliases = explode('|', str_replace('%', '%%', $aliases));
        $commandName = array_shift($aliases);

        if ($isHidden = '' === $commandName) {
            $commandName = array_shift($aliases);
        }

        if (null === $commandName) {
            if ($definition->isPrivate() || $definition->hasTag('container.private')) {
                $commandId = 'console.command.public_alias.'.$id;
                $container->setAlias($commandId, $id)->setPublic(true);
                $id = $commandId;
            }
            $serviceIds[] = $id;

            return;
        }

        $description = $tags[0]['description'] ?? null;
        $help = $tags[0]['help'] ?? null;
        $usages = $tags[0]['usages'] ?? null;

        unset($tags[0]);
        $lazyCommandMap[$commandName] = $id;
        $lazyCommandRefs[$id] = new TypedReference($id, $class);

        foreach ($aliases as $alias) {
            $lazyCommandMap[$alias] = $id;
        }

        foreach ($tags as $tag) {
            if (isset($tag['command'])) {
                $aliases[] = $tag['command'];
                $lazyCommandMap[$tag['command']] = $id;
            }

            $description ??= $tag['description'] ?? null;
            $help ??= $tag['help'] ?? null;
            $usages ??= $tag['usages'] ?? null;
        }

        $definition->addMethodCall('setName', [$commandName]);

        if ($aliases) {
            $definition->addMethodCall('setAliases', [$aliases]);
        }

        if ($isHidden) {
            $definition->addMethodCall('setHidden', [true]);
        }

        if ($help ??= $attribute?->help) {
            $definition->addMethodCall('setHelp', [str_replace('%', '%%', $help)]);
        }

        if ($usages ??= $attribute?->usages) {
            foreach ($usages as $usage) {
                $definition->addMethodCall('addUsage', [$usage]);
            }
        }

        if ($description ??= $attribute?->description) {
            $escapedDescription = str_replace('%', '%%', $description);
            $definition->addMethodCall('setDescription', [$escapedDescription]);

            $container->register('.'.$id.'.lazy', LazyCommand::class)
                ->setArguments([$commandName, $aliases, $escapedDescription, $isHidden, new ServiceClosureArgument($lazyCommandRefs[$id])]);

            $lazyCommandRefs[$id] = new Reference('.'.$id.'.lazy');
        }
    }

    private function getCommandAttribute(\ReflectionClass|\ReflectionMethod $reflection): ?AsCommand
    {
        /** @var AsCommand|null $attribute */
        $attribute = ($reflection->getAttributes(AsCommand::class)[0] ?? null)?->newInstance();

        if ($reflection instanceof \ReflectionMethod && '__invoke' === $reflection->getName()) {
            $classAttribute = $reflection->getDeclaringClass()->getAttributes(AsCommand::class)[0] ?? null;

            if ($attribute && $classAttribute) {
                throw new InvalidArgumentException(\sprintf('The "%s" class and its "__invoke()" method cannot both have the "%s" attribute.', $reflection->class, AsCommand::class));
            }

            $attribute ??= $classAttribute?->newInstance();
        }

        return $attribute;
    }
}
