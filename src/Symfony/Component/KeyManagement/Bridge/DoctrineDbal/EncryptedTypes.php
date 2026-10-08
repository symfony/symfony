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

use Doctrine\DBAL\Types\Type;
use Symfony\Component\KeyManagement\EnvelopeDecrypterInterface;
use Symfony\Component\KeyManagement\EnvelopeEncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

/**
 * Declares a set of {@see EncryptedType} on the Doctrine type registry.
 *
 * An EncryptedType takes an encrypter, which is a service, so it cannot be declared through
 * `doctrine.dbal.types`: that option names a class and DoctrineBundle instantiates it with no
 * argument. This is what an application declares instead, as one service per encrypter:
 *
 *     app.encrypted_types:
 *         class: Symfony\Component\KeyManagement\Bridge\DoctrineDbal\EncryptedTypes
 *         public: true
 *         arguments:
 *             $envelopes: '@key_management.stored_envelope_encrypter'
 *             $types:
 *                 encrypted_email: { type: string, key: 'user.email' }
 *                 encrypted_notes: { type: text, key: 'user.notes' }
 *
 * Add `context: false` to a declaration whose backend refuses authenticated data.
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
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class EncryptedTypes
{
    /**
     * @param array<string, array{type: string, key: string, context?: bool}> $types Name of the type to declare, mapped to the Doctrine type it wraps, to the key or scope it encrypts under, and to whether it authenticates its own context, {@see EncryptedType}
     */
    public function __construct(
        private readonly EnvelopeEncrypterInterface&EnvelopeDecrypterInterface $envelopes,
        private readonly array $types,
    ) {
    }

    /**
     * Replaces a type whose name is taken rather than refusing to add it.
     *
     * That is what a rebooted kernel needs: the type registry is global and outlives the
     * container, so the types of the previous one are still there, pointing at encrypters nothing
     * else uses any more.
     *
     * The name a type is registered under is what it authenticates its values with, so renaming one
     * makes the values written under the former name unreadable.
     *
     * @throws InvalidArgumentException If a declaration names no Doctrine type or no key
     */
    public function register(): void
    {
        $registry = Type::getTypeRegistry();

        foreach ($this->types as $name => $definition) {
            foreach (['type', 'key'] as $required) {
                if (!isset($definition[$required]) || !\is_string($definition[$required])) {
                    throw new InvalidArgumentException(\sprintf('The encrypted type "%s" must declare a "%s" as a string.', $name, $required));
                }
            }

            if (isset($definition['context']) && !\is_bool($definition['context'])) {
                throw new InvalidArgumentException(\sprintf('The encrypted type "%s" must declare its "context" as a boolean.', $name));
            }

            $type = new EncryptedType($registry->get($definition['type']), $this->envelopes, $definition['key'], $name, $definition['context'] ?? true);

            $registry->has($name) ? $registry->override($name, $type) : $registry->register($name, $type);
        }
    }
}
