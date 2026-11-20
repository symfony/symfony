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
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;

/**
 * Composite {@see KmsFactoryInterface} delegating to the first factory that supports a DSN.
 *
 * Bridges register themselves with the `key_management.factory` DI tag, so
 * installing a new bridge is enough to extend the set of supported schemes.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class FactoryRegistry implements KmsFactoryInterface
{
    /** @var list<KmsFactoryInterface> */
    private array $loadedFactories = [];

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

    public function supports(#[\SensitiveParameter] Dsn $dsn): bool
    {
        foreach ($this->getFactories() as $factory) {
            if ($factory->supports($dsn)) {
                return true;
            }
        }

        return false;
    }

    public function create(#[\SensitiveParameter] Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        foreach ($this->getFactories() as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn);
            }
        }

        if ('service' === $dsn->scheme) {
            throw new InvalidArgumentException('A "service://" KMS DSN is resolved when the container is compiled, so it cannot be read from an environment variable.');
        }

        throw new UnsupportedSchemeException($dsn);
    }

    /**
     * @return iterable<KmsFactoryInterface>
     */
    private function getFactories(): iterable
    {
        if (!$this->factories instanceof \Generator) {
            yield from $this->factories;

            return;
        }

        for ($i = 0;; ++$i) {
            if (!isset($this->loadedFactories[$i])) {
                if ($i > 0) {
                    $this->factories->next();
                }
                if (!$this->factories->valid()) {
                    return;
                }
                $this->loadedFactories[] = $this->factories->current();
            }

            yield $this->loadedFactories[$i];
        }
    }
}
