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
    private array $createdTransports = [];

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        ['serialize' => $serialize] = $this->parseDsn($dsn);

        return $this->createdTransports[] = new InMemoryTransport($serialize ? $serializer : null);
    }

    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'in-memory://');
    }

    public function reset()
    {
        foreach ($this->createdTransports as $transport) {
            $transport->reset();
        }
    }

    private function parseDsn(string $dsn): array
    {
        $query = [];
        if ($queryAsString = strstr($dsn, '?')) {
            parse_str(ltrim($queryAsString, '?'), $query);
        }

        return [
            'serialize' => filter_var($query['serialize'] ?? false, \FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
