<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\DependencyInjection;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DefaultTypedFieldMapper;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\MappedSuperclass;
use Symfony\Component\DependencyInjection\Argument\LazyProxyArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

/**
 * Registers the encrypted types and entity manager configurators needed by {@see Encrypted} columns.
 *
 * Explicit keys use types collected during container compilation, including matching configured declarations.
 * {@see self::autoconfigure()} tags the entities, embeddables and mapped superclasses that the container loads with their encrypted columns.
 * The ".key_management.doctrine_dbal.types" parameter holds the types declared in "key_management.doctrine_dbal.types".
 * This pass adds one type per key and type to them.
 * A declared type is used for the columns of its key and type, instead of registering another one.
 * The ".key_management.encrypted_column_classes" parameter lists the classes that use each registered type.
 *
 * Columns whose key is omitted get their scope from resolved ORM metadata after the entity manager is constructed.
 * Their types are found once per entity manager and kept in the "cache.key_management.encrypted_columns" pool, which doesn't exist in debug mode.
 *
 * {@see \Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener\EncryptedColumnListener} gets the names of all these types.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental
 */
final class RegisterEncryptedColumnsPass implements CompilerPassInterface
{
    private const string TAG = 'key_management.encrypted_column';
    private const string LISTENER = 'key_management.encrypted_column_listener';
    private const string TYPES = '.key_management.doctrine_dbal.types';
    private const string CLASSES = '.key_management.encrypted_column_classes';
    private const string CACHE = 'cache.key_management.encrypted_columns';

    /**
     * Tags a class mapped by the ORM with the key and the type of each of its encrypted columns.
     *
     * The type is the one that the ORM gives to the column.
     * It's the type declared on the column, or the type guessed from the type of the property.
     * The parent classes are read too, since a parent can be outside of the directories the container loads, or not mapped by the ORM.
     */
    public static function autoconfigure(ChildDefinition $definition, Entity|Embeddable|MappedSuperclass $mapping, \ReflectionClass $class): void
    {
        for ($reflection = $class; $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if ($property->class !== $reflection->name || !$attribute = $property->getAttributes(Encrypted::class)[0] ?? null) {
                    continue;
                }

                $definition->addResourceTag(self::TAG, ['key' => $attribute->newInstance()->key, 'type' => self::typeOf($property)]);
            }
        }
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::LISTENER)) {
            return;
        }

        $types = $container->hasParameter(self::TYPES) ? $container->getParameter(self::TYPES) : null;

        $names = [];
        foreach ($types ?? [] as $name => $type) {
            $names[$type['key']][$type['type']] ??= $name;
        }

        $automatic = false;
        $classes = [];
        foreach ($container->findTaggedResourceIds(self::TAG, false) as $id => $tags) {
            $class = $container->getDefinition($id)->getClass();

            foreach ($tags as ['key' => $key, 'type' => $type]) {
                if (null === $key) {
                    if (!$container->hasDefinition('key_management.stored_envelope_encrypter')) {
                        throw new \LogicException(\sprintf('An encrypted column of "%s" omits its key and needs a data key store: configure "key_management.store", or set an explicit key.', $class));
                    }

                    $automatic = true;
                    continue;
                }

                if (!isset($names[$key][$type])) {
                    if (null === $types) {
                        throw new \LogicException(\sprintf('The encrypted columns of "%s" need an envelope encrypter: configure "key_management.store", or set "key_management.default_client".', $class));
                    }

                    $name = 'encrypted_'.$type.'@'.$key;

                    if (isset($types[$name])) {
                        throw new \LogicException(\sprintf('The encrypted type "%s" is configured to wrap the "%s" type under the key "%s", but that name is the one given to the encrypted columns of type "%s" under that key. Rename it.', $name, $types[$name]['type'], $types[$name]['key'], $type));
                    }

                    $types[$name] = ['type' => $type, 'key' => $key];
                    $names[$key][$type] = $name;
                    $classes[$name] = [];
                }

                if (isset($classes[$names[$key][$type]])) {
                    $classes[$names[$key][$type]][$class] = $class;
                }
            }
        }

        $container->getDefinition(self::LISTENER)->setArgument(0, $names);
        $container->setParameter(self::CLASSES, array_map(array_values(...), $classes));

        if ($automatic) {
            $container->getDefinition(self::LISTENER)
                ->setArgument(1, new LazyProxyArgument(new Reference('key_management.stored_envelope_encrypter')))
                ->setArgument(2, new Reference(self::CACHE, ContainerInterface::NULL_ON_INVALID_REFERENCE));

            foreach ($container->hasParameter('doctrine.entity_managers') ? $container->getParameter('doctrine.entity_managers') : [] as $name => $id) {
                $manager = $container->findDefinition($id);
                $previous = $manager->getConfigurator();
                $configurator = null === $previous ? null : (new Definition(\Closure::class))->setFactory([\Closure::class, 'fromCallable'])->setArguments([$previous]);
                $configuratorId = '.key_management.configure_encrypted_columns.'.$id;
                $container->register($configuratorId, ConfigureEncryptedColumns::class)->setArguments([new Reference(self::LISTENER), $name, $configurator]);
                $manager->setConfigurator([new Reference($configuratorId), '__invoke']);
            }
        }

        if (null !== $types) {
            $container->setParameter(self::TYPES, $types);
        }
    }

    private static function typeOf(\ReflectionProperty $property): string
    {
        $mapping = ['fieldName' => $property->name];

        if (null !== $type = ($property->getAttributes(Column::class)[0] ?? null)?->newInstance()->type) {
            $mapping['type'] = $type;
        }

        if ($property->hasType()) {
            $mapping = (new DefaultTypedFieldMapper())->validateAndComplete($mapping, $property);
        }

        return $mapping['type'] ?? 'string';
    }
}
