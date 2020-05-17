<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Transport;

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TransportFactory
{
    private $factories;

    /**
     * @param iterable|TransportFactoryInterface[] $transportsFactories
     */
    public function __construct(iterable $transportsFactories)
    {
        $this->factories = $transportsFactories;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->support($dsn, $options)) {
                return $factory->createTransport(Dsn::fromString($dsn), $options, $serializer);
            }
        }

        // Help the user to select Symfony packages based on DSN.
        $packageSuggestion = '';
        if ('kubernetes://' === substr($dsn, 0, 10) || 'k8s://' === substr($dsn, 0, 3)) {
            $packageSuggestion = ' Run "composer require symfony/kubernetes-scheduler" to install Kubernetes transport.';
        }

        if ('google://' === substr($dsn, 0, 6)) {
            $packageSuggestion = ' Run "composer require symfony/google-scheduler" to install Google Cloud Platform transport.';
        }

        if ('redis://' === substr($dsn, 0, 5)) {
            $packageSuggestion = ' Run "composer require symfony/redis-scheduler" to install Redis transport.';
        }

        if ('doctrine://' === substr($dsn, 0, 8)) {
            $packageSuggestion = ' Run "composer require symfony/doctrine-scheduler" to install Doctrine transport.';
        }

        throw new InvalidArgumentException(sprintf('No transport supports the given Scheduler DSN "%s".%s', $dsn, $packageSuggestion));
    }
}
