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

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class InMemoryTransportFactory implements TransportFactoryInterface, ResetInterface
{
    /**
     * @var InMemoryTransport[]
     */
    private $createdTransports = [];

    public function createTransport(Dsn $dsn, SerializerInterface $serializer, string $name): TransportInterface
    {
        return $this->createdTransports[] = new InMemoryTransport();
    }

    public function supports(Dsn $dsn): bool
    {
        return 'in-memory' === $dsn->getScheme();
    }

    public function reset()
    {
        foreach ($this->createdTransports as $transport) {
            $transport->reset();
        }
    }
}
