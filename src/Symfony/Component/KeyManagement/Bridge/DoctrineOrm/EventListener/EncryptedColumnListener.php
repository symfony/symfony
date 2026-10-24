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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes;
use Symfony\Component\KeyManagement\Bridge\DoctrineOrm\Attribute\Encrypted;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;

/**
 * Gives the columns marked with {@see Encrypted} their encrypted type.
 *
 * Explicit keys use types registered before the first query.
 * Omitted keys use a data key store and a scope derived from the physical column.
 * Call register() after creating the entity manager to register these types from cold or cached metadata.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental
 */
final class EncryptedColumnListener
{
    private const string AUTOMATIC_TYPE_PREFIX = 'key_management_auto_v1_';

    /**
     * @param array<string, array<string, string>>                         $types              The names of the encrypted types, by key and by the type they wrap
     * @param (EnvelopeEncrypterInterface&EnvelopeDecrypterInterface)|null $automaticEnvelopes Store-backed encrypter for columns whose key is omitted
     */
    public function __construct(
        private readonly array $types,
        private readonly (EnvelopeEncrypterInterface&EnvelopeDecrypterInterface)|null $automaticEnvelopes = null,
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

                if (null === $key) {
                    continue;
                }

                $mapping->type = $this->types[$key][$mapping->type] ?? throw new \LogicException(\sprintf('No encrypted type is registered for the key "%s" and the "%s" type of "%s::$%s". The KeyManagementBundle registers the types of the "%s" attributes it finds in the "resource" directories of your services: check that "%s" is there and not excluded, or declare an encrypted type with that key and type in "key_management.doctrine_dbal.types".', $key, $mapping->type, $class, $property->name, Encrypted::class, $class));
            }
        }

        $this->registerAutomaticTypes($metadata, $event->getEntityManager());
    }

    /**
     * Registers automatically scoped types from resolved metadata, including cached mappings.
     */
    public function register(EntityManagerInterface $manager): void
    {
        foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
            $this->registerAutomaticTypes($metadata, $manager);
        }
    }

    private function registerAutomaticTypes(ClassMetadata $metadata, EntityManagerInterface $manager): void
    {
        if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
            return;
        }

        foreach ($metadata->fieldMappings as $mapping) {
            $property = new \ReflectionProperty($mapping->originalClass ?? $mapping->declared ?? $metadata->getName(), $mapping->originalField ?? $mapping->fieldName);
            $attribute = $property->getAttributes(Encrypted::class)[0] ?? null;

            if (!$attribute || null !== $attribute->newInstance()->key) {
                continue;
            }

            if (null === $this->automaticEnvelopes) {
                throw new \LogicException(\sprintf('The encrypted column "%s::$%s" omits its key and needs a data key store: configure "key_management.store", or set an explicit key.', $metadata->getName(), $mapping->fieldName));
            }

            $owner = $metadata->isInheritanceTypeJoined() && isset($mapping->inherited) ? $manager->getClassMetadata($mapping->inherited) : $metadata;
            $hash = hash('sha256', serialize([$owner->getSchemaName(), $owner->getTableName(), $mapping->columnName]));
            $scope = 'orm:'.$hash;
            $parent = $mapping->type;
            if (str_starts_with($parent, self::AUTOMATIC_TYPE_PREFIX)) {
                $encoded = explode('.', substr($parent, \strlen(self::AUTOMATIC_TYPE_PREFIX)), 2);
                if (2 === \count($encoded) && preg_match('/^[0-9a-f]{64}$/D', $encoded[1]) && false !== $decoded = base64_decode($encoded[0], true)) {
                    if (base64_encode($decoded) === $encoded[0]) {
                        $parent = $decoded;
                    }
                }
            }

            $name = self::AUTOMATIC_TYPE_PREFIX.base64_encode($parent).'.'.$hash;
            (new EncryptedTypes($this->automaticEnvelopes, [$name => ['type' => $parent, 'key' => $scope]]))->register($manager->getConnection());
            $mapping->type = $name;
        }
    }
}
