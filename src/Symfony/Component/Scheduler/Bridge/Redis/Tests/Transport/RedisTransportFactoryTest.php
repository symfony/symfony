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
use Symfony\Component\Scheduler\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Scheduler\Task\TaskFactoryInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 */
final class RedisTransportFactoryTest extends TestCase
{
    public function testTransportCanSupport(): void
    {
        $taskFactory = $this->createMock(TaskFactoryInterface::class);
        $factory = new RedisTransportFactory($taskFactory);

        static::assertFalse($factory->support('test://'));
        static::assertTrue($factory->support('redis://'));
    }
}
