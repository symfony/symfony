<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\DoctrineDbal;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\TypeRegistry;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

/**
 * Declares a set of {@see EncryptedType} on the Doctrine type registry.
 *
 * An EncryptedType takes an encrypter, which is a service, so it cannot be declared through
 * `doctrine.dbal.types`: that option names a class and DoctrineBundle instantiates it with no
 * argument. KeyManagementBundle declares one from its `key_management.doctrine_dbal.types`
 * configuration. Without it, this is what an application declares instead, as one service per
 * encrypter:
 *
 *     app.encrypted_types:
 *         class: Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes
 *         public: true
 *         arguments:
 *             $envelopes: '@app.envelope_encrypter'
 *             $types:
 *                 encrypted_email: { type: string, key: 'user.email' }
 *                 encrypted_notes: { type: text, key: 'user.notes' }
 *
 * and calls once the container is built, which in a Symfony application means booting:
 *
 *     public function boot(): void
 *     {
 *         parent::boot();
 *
 *         $this->container->get('app.encrypted_types')->register();
 *     }
 *
 * Registering there rather than later is what makes the types available to every entry point at
 * once: the front controller, the console with its schema tool and its migrations, and the test
 * kernel. Nothing is opened by doing so, since a DBAL connection only reaches the database on its
 * first query.
 *
 * KeyManagementBundle calls register() when DoctrineBundle creates a connection instead. The types
 * can then wrap the ones DoctrineBundle registers at that time.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class EncryptedTypes
{
    /**
     * @param array<string, array{type: string, key: string}> $types Name of the type to declare, mapped to the Doctrine type it wraps and to the key or scope it encrypts under
     */
    public function __construct(
        private readonly EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $envelopes,
        private readonly array $types,
    ) {
    }

    /**
     * Declares the types on the connection's registry, or the global registry when no connection is given.
     *
     * When a connection is given, the parent types are the ones this connection resolves, so that a type registered on this connection can be wrapped too.
     *
     * Replaces a type whose name is taken rather than refusing to add it.
     * That is what a rebooted kernel needs: the type registry is global and outlives the
     * container, so the types of the previous one are still there, pointing at encrypters nothing
     * else uses any more.
     *
     * A type authenticates its key and the parent type's registry name.
     * Renaming the encrypted declaration leaves every value readable, {@see EncryptedType}.
     *
     * @throws InvalidArgumentException If a declaration is invalid, has circular parents, or the registry is not writable
     */
    public function register(?Connection $connection = null): void
    {
        $configuration = $connection?->getConfiguration() ?? new Configuration();
        $registry = method_exists($configuration, 'getTypeProvider') ? $configuration->getTypeProvider() : Type::getTypeRegistry();

        if (!$registry instanceof TypeRegistry) {
            throw new InvalidArgumentException('Encrypted types require a writable Doctrine type registry.');
        }

        foreach ($this->types as $name => $definition) {
            foreach (['type', 'key'] as $required) {
                if (!isset($definition[$required]) || !\is_string($definition[$required])) {
                    throw new InvalidArgumentException(\sprintf('The encrypted type "%s" must declare a "%s" as a string.', $name, $required));
                }
            }
        }

        $types = [];
        foreach ($this->types as $name => $definition) {
            $types[$name] ??= $this->build($name, $registry, $types, []);
        }

        foreach ($types as $name => $type) {
            $registry->has($name) ? $registry->override($name, $type) : $registry->register($name, $type);
        }
    }

    /**
     * Builds the parent first when it is declared here too, whatever the order of the declarations.
     *
     * @param array<string, EncryptedType> $types
     * @param array<string, true>          $children
     */
    private function build(string $name, TypeRegistry $registry, array &$types, array $children): EncryptedType
    {
        if (isset($children[$name])) {
            throw new InvalidArgumentException(\sprintf('The encrypted type "%s" has a circular parent type definition.', $name));
        }

        $parent = $this->types[$name]['type'];
        $parentType = isset($this->types[$parent]) ? $types[$parent] ?? $this->build($parent, $registry, $types, $children + [$name => true]) : $registry->get($parent);

        return $types[$name] = new EncryptedType($parentType, $this->envelopes, $this->types[$name]['key'], $parent);
    }
}
