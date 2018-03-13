<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Adapter\Factory;

use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\SenderInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ChainAdapterFactory implements AdapterFactoryInterface
{
    private $factories;

    /**
     * @param iterable|AdapterFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function createReceiver(string $dsn, array $options): ReceiverInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn, $options)) {
                return $factory->createReceiver($dsn, $options);
            }
        }

        throw new \InvalidArgumentException(sprintf('No adapter supports the given DSN "%s".', $dsn));
    }

    public function createSender(string $dsn, array $options): SenderInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn, $options)) {
                return $factory->createSender($dsn, $options);
            }
        }

        throw new \InvalidArgumentException(sprintf('No adapter supports the given DSN "%s".', $dsn));
    }

    public function supports(string $dsn, array $options): bool
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn, $options)) {
                return true;
            }
        }

        return false;
    }
}
