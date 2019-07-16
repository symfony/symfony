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

use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/**
 * Creates a Messenger transport.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
interface TransportFactoryInterface
{
    /**
     * @param string $name Transport name
     *
     * @throws TransportException In case when transport couldn't be created
     */
    public function createTransport(Dsn $dsn, SerializerInterface $serializer, string $name): TransportInterface;

    public function supports(Dsn $dsn): bool;
}
