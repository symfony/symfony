<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Factory;

use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;

/**
 * Composite {@see KmsFactoryInterface} that delegates to the first registered
 * factory whose `supports()` returns true. Bridges register themselves with
 * the `key_management.factory` DI tag so installing a new bridge is enough to
 * extend the set of supported schemes.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class FactoryRegistry implements KmsFactoryInterface
{
    /**
     * @param iterable<KmsFactoryInterface> $factories
     */
    public function __construct(
        private readonly iterable $factories,
    ) {
    }

    public function fromString(#[\SensitiveParameter] string $dsn): EncrypterInterface&DecrypterInterface
    {
        return $this->create(Dsn::fromString($dsn));
    }

    public function supports(Dsn $dsn): bool
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return true;
            }
        }

        return false;
    }

    public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }
}
