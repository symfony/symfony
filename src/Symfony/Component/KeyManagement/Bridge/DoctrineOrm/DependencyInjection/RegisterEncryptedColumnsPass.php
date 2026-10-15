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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

/**
 * Registers an encrypted type for each key and type of the columns marked with {@see Encrypted}.
 *
 * The types must be registered before the first query, even when the metadata comes from the cache, so they are collected when the container is compiled.
 * {@see self::autoconfigure()} tags the entities, embeddables and mapped superclasses that the container loads with their encrypted columns.
 * This pass then adds one type per key and type to the types declared in "key_management.doctrine_dbal.types".
 * A declared type is used for the columns of its key and type, instead of registering another one.
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
    private const string TYPES = 'key_management.doctrine_dbal.encrypted_types';

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

        $types = $container->hasDefinition(self::TYPES) ? $container->getDefinition(self::TYPES)->getArgument(1) : null;

        $names = [];
        foreach ($types ?? [] as $name => $type) {
            $names[$type['key']][$type['type']] ??= $name;
        }

        foreach ($container->findTaggedResourceIds(self::TAG, false) as $id => $tags) {
            foreach ($tags as ['key' => $key, 'type' => $type]) {
                if (isset($names[$key][$type])) {
                    continue;
                }

                if (null === $types) {
                    throw new \LogicException(\sprintf('The encrypted columns of "%s" need an envelope encrypter: configure "key_management.store", or set "key_management.default_client".', $container->getDefinition($id)->getClass()));
                }

                $name = 'encrypted_'.$type.'@'.$key;

                if (isset($types[$name])) {
                    throw new \LogicException(\sprintf('The encrypted type "%s" is configured to wrap the "%s" type under the key "%s", but that name is the one given to the encrypted columns of type "%s" under that key. Rename it.', $name, $types[$name]['type'], $types[$name]['key'], $type));
                }

                $types[$name] = ['type' => $type, 'key' => $key];
                $names[$key][$type] = $name;
            }
        }

        $container->getDefinition(self::LISTENER)->setArgument(0, $names);

        if (!$types) {
            $container->removeDefinition(self::TYPES);
        } else {
            $container->getDefinition(self::TYPES)->setArgument(1, $types);
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
