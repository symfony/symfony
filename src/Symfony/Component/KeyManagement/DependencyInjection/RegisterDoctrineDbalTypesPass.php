<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\DependencyInjection;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Declares the encrypted Doctrine DBAL types as services tagged "doctrine.dbal.type", which DoctrineBundle gives to its connections.
 *
 * A type of "key_management.doctrine_dbal.types" is declared on every connection.
 * The type of an encrypted column is declared on the connections of the entity managers that map its class.
 * The parent type is resolved by the first of these connections, or by the default connection.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class RegisterDoctrineDbalTypesPass implements CompilerPassInterface
{
    private const string TYPES = '.key_management.doctrine_dbal.types';
    private const string CLASSES = '.key_management.encrypted_column_classes';
    private const string PROTOTYPE = '.key_management.doctrine_dbal.encrypted_type';

    public function process(ContainerBuilder $container): void
    {
        if (!$types = $container->hasParameter(self::TYPES) ? $container->getParameter(self::TYPES) : []) {
            return;
        }

        if (!$container->hasParameter('doctrine.connections')) {
            throw new LogicException(\sprintf('The encrypted Doctrine DBAL types "%s" can only be declared on the connections of DoctrineBundle. Try running "composer require doctrine/doctrine-bundle".', implode('", "', array_keys($types))));
        }

        $prototype = $container->getDefinition(self::PROTOTYPE);
        $connections = $container->getParameter('doctrine.connections');
        $default = $container->hasParameter('doctrine.default_connection') ? $container->getParameter('doctrine.default_connection') : array_key_first($connections);
        $classes = $container->hasParameter(self::CLASSES) ? $container->getParameter(self::CLASSES) : [];
        $namespaces = $this->getConnectionsByNamespace($container, $connections);

        foreach ($types as $name => ['type' => $parent, 'key' => $key]) {
            $names = [];
            foreach ($classes[$name] ?? [] as $class) {
                foreach ($namespaces as $namespace => $connectionNames) {
                    if (str_starts_with($class, $namespace)) {
                        $names += array_flip($connectionNames);
                    }
                }
            }
            $names = array_keys($names);

            $parentType = (new Definition(Type::class))
                ->setFactory([(new Definition(TypeProvider::class))->setFactory([new Reference(\sprintf('doctrine.dbal.%s_connection.configuration', $names[0] ?? $default)), 'getTypeProvider']), 'get'])
                ->setArguments([$parent]);

            $definition = $container->setDefinition(self::PROTOTYPE.'.'.$name, (clone $prototype)->setAbstract(false))
                ->replaceArgument(0, $parentType)
                ->replaceArgument(2, $key);

            // without a connection, DoctrineBundle declares the type on every connection
            foreach ($names ?: [null] as $connection) {
                $definition->addTag('doctrine.dbal.type', ['type_name' => $name] + (null !== $connection ? ['connection' => $connection] : []));
            }
        }
    }

    /**
     * @param array<string, string> $connections
     *
     * @return array<string, list<string>> The names of the connections of the entity managers, by namespace of the classes they map
     */
    private function getConnectionsByNamespace(ContainerBuilder $container, array $connections): array
    {
        $namespaces = [];

        foreach ($container->hasParameter('doctrine.entity_managers') ? $container->getParameter('doctrine.entity_managers') : [] as $name => $id) {
            $driver = \sprintf('doctrine.orm.%s_metadata_driver', $name);

            if (!$container->hasDefinition($id) || !$container->hasDefinition($driver)) {
                continue;
            }

            $connection = $container->getDefinition($id)->getArguments()[0] ?? null;

            if (!$connection instanceof Reference || false === $connection = array_search((string) $connection, $connections, true)) {
                continue;
            }

            foreach ($container->getDefinition($driver)->getMethodCalls() as [$method, $arguments]) {
                if ('addDriver' === $method) {
                    $namespaces[$arguments[1]][] = $connection;
                } elseif ('setDefaultDriver' === $method) {
                    $namespaces[''][] = $connection;
                }
            }
        }

        return $namespaces;
    }
}
