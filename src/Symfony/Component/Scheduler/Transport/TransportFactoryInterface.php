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

use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
interface TransportFactoryInterface
{
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer): TransportInterface;

    public function support(string $dsn, array $options = []): bool;
}
