<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Redis\Transport\RedisTransport;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class RedisTransportTest extends TestCase
{
    public function testDeleteThrowExceptionIfNoKeyIsDeleted(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $factory = $this->createMock(TaskFactoryInterface::class);

        $transport = new RedisTransport(Dsn::fromString('redis://localhost/tasks'), [], $serializer, $factory);
    }
}
