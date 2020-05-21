<?php

declare(strict_types=1);

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
    /**
     * @param Dsn                 $dsn
     * @param array<string,mixed> $options
     * @param SerializerInterface $serializer
     *
     * @return TransportInterface
     */
    public function createTransport(Dsn $dsn, array $options, SerializerInterface $serializer): TransportInterface;

    /**
     * @param string              $dsn
     * @param array<string,mixed> $options
     *
     * @return bool
     */
    public function support(string $dsn, array $options = []): bool;
}
