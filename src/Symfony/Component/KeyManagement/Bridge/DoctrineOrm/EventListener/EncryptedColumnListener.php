<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineOrm\EventListener;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;

/**
 * Gives the columns marked with {@see Encrypted} the encrypted type registered for their key and their type.
 *
 * It runs when the metadata of a class is loaded, so the metadata cache keeps the encrypted type.
 * This is also why the listener doesn't register the types. It never runs when the cache is warm.
 * The types must be registered before the first query, as the KeyManagementBundle does when DoctrineBundle creates a connection.
 *
 * A column inherited from a parent class, or inlined from an embeddable, got its type when the metadata of that class was loaded.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental
 */
final class EncryptedColumnListener
{
    /**
     * @param array<string, array<string, string>> $types The names of the encrypted types, by key and by the type they wrap
     */
    public function __construct(
        private readonly array $types,
    ) {
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $event): void
    {
        $metadata = $event->getClassMetadata();
        $class = $metadata->getName();
        $seen = [];

        for ($reflection = $metadata->getReflectionClass(); $reflection; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getProperties() as $property) {
                if (isset($seen[$property->name])) {
                    continue;
                }
                $seen[$property->name] = true;

                if (!$attribute = $property->getAttributes(Encrypted::class)[0] ?? null) {
                    continue;
                }

                if (!$metadata->hasField($property->name)) {
                    throw new \LogicException(\sprintf('The property "%s::$%s" has the "%s" attribute but is not a mapped column. Only the value of a column can be encrypted.', $class, $property->name, Encrypted::class));
                }

                $mapping = $metadata->getFieldMapping($property->name);

                if (isset($mapping->declared)) {
                    continue;
                }

                $key = $attribute->newInstance()->key;

                $mapping->type = $this->types[$key][$mapping->type] ?? throw new \LogicException(\sprintf('No encrypted type is registered for the key "%s" and the "%s" type of "%s::$%s". The KeyManagementBundle registers the types of the "%s" attributes it finds in the "resource" directories of your services: check that "%s" is there and not excluded, or declare an encrypted type with that key and type in "key_management.doctrine_dbal.types".', $key, $mapping->type, $class, $property->name, Encrypted::class, $class));
            }
        }
    }
}
