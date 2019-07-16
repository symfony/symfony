<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class TransportFactory implements TransportFactoryInterface
{
    private $factories;

    /**
     * @param iterable|TransportFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function fromString(string $dsn, array $options, SerializerInterface $serializer, string $name): TransportInterface
    {
        return $this->createTransport(Dsn::fromString($dsn, $options), $serializer, $name);
    }

    public function createTransport(Dsn $dsn, SerializerInterface $serializer, string $name): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->createTransport($dsn, $serializer, $name);
            }
        }

        throw new InvalidArgumentException(sprintf('No transport supports the given Messenger DSN "%s".', $dsn));
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
}
