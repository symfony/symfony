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
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use Psr\Cache\CacheItemPoolInterface;
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
 * Give the listener a cache to find these types once instead of loading all the metadata each time.
 * Add the listener after the listeners that rename tables or columns, so that it computes scopes from the final names.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @experimental
 */
final class EncryptedColumnListener
{
    private const string AUTOMATIC_TYPE_PREFIX = 'key_management_auto_v1_';

    /**
     * The metadata factory that register() uses while it finds the types.
     */
    private ?ClassMetadataFactory $factory = null;

    /**
     * @param array<string, array<string, string>>                         $types              The names of the encrypted types, by key and by the type they wrap
     * @param (EnvelopeEncrypterInterface&EnvelopeDecrypterInterface)|null $automaticEnvelopes Store-backed encrypter for columns whose key is omitted
     * @param CacheItemPoolInterface|null                                  $cache              Keeps the types of the columns whose key is omitted, by entity manager
     */
    public function __construct(
        private readonly array $types,
        private readonly (EnvelopeEncrypterInterface&EnvelopeDecrypterInterface)|null $automaticEnvelopes = null,
        private readonly ?CacheItemPoolInterface $cache = null,
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

        $this->registerAutomaticTypes($this->automaticTypes($metadata, $event->getEntityManager()), $event->getEntityManager());
    }

    /**
     * Registers automatically scoped types from resolved metadata, including cached mappings.
     *
     * Finding these types loads the metadata of every mapped class.
     * With a cache, the types found are saved under the name of the entity manager, and the next calls load no metadata.
     *
     * The metadata is loaded with a factory of its own, so the factory of the manager stays empty.
     * The metadata cache warmer of DoctrineBundle requires this.
     * When a listener added after this one renamed a table or a column, the metadata cache has names that aren't the final ones.
     * The types are then not cached, and the metadata of the manager is loaded to fix the names in it.
     *
     * @param string $name The name of the entity manager, which keys its types in the cache
     */
    public function register(EntityManagerInterface $manager, string $name = 'default'): void
    {
        $item = $this->cache?->getItem('automatic_types.'.hash('xxh128', $name));

        if ($item?->isHit()) {
            $this->registerAutomaticTypes($item->get(), $manager);

            return;
        }

        $configuration = $manager->getConfiguration();
        $this->factory = new ($configuration->getClassMetadataFactoryName())();
        $this->factory->setEntityManager($manager);
        if (null !== $metadataCache = $configuration->getMetadataCache()) {
            $this->factory->setCache($metadataCache);
        }

        $types = [];
        $renamed = false;
        try {
            foreach ($this->factory->getAllMetadata() as $metadata) {
                $types += $this->automaticTypes($metadata, $manager, $renamed);
            }
        } finally {
            $this->factory = null;
        }

        if ($renamed) {
            $types = [];
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                $types += $this->automaticTypes($metadata, $manager);
            }
        } elseif (null !== $item) {
            $this->cache->save($item->set($types));
        }

        $this->registerAutomaticTypes($types, $manager);
    }

    /**
     * @param bool $renamed Set to true when a column had another type name, computed before a table or a column was renamed
     *
     * @return array<string, array{type: string, key: string}>
     */
    private function automaticTypes(ClassMetadata $metadata, EntityManagerInterface $manager, bool &$renamed = false): array
    {
        if ($metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
            return [];
        }

        $types = [];
        foreach ($metadata->fieldMappings as $mapping) {
            $property = new \ReflectionProperty($mapping->originalClass ?? $mapping->declared ?? $metadata->getName(), $mapping->originalField ?? $mapping->fieldName);
            $attribute = $property->getAttributes(Encrypted::class)[0] ?? null;

            if (!$attribute || null !== $attribute->newInstance()->key) {
                continue;
            }

            if (null === $this->automaticEnvelopes) {
                throw new \LogicException(\sprintf('The encrypted column "%s::$%s" omits its key and needs a data key store: configure "key_management.store", or set an explicit key.', $metadata->getName(), $mapping->fieldName));
            }

            $owner = $metadata->isInheritanceTypeJoined() && isset($mapping->inherited) ? ($this->factory ?? $manager->getMetadataFactory())->getMetadataFor($mapping->inherited) : $metadata;
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
            $types[$name] = ['type' => $parent, 'key' => $scope];
            $renamed = $renamed || $name !== $mapping->type;
            $mapping->type = $name;
        }

        return $types;
    }

    /**
     * @param array<string, array{type: string, key: string}> $types
     */
    private function registerAutomaticTypes(array $types, EntityManagerInterface $manager): void
    {
        if ($types) {
            (new EncryptedTypes($this->automaticEnvelopes, $types))->register($manager->getConnection());
        }
    }
}
