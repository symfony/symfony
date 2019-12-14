<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Cron;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Cron\CronFactory;
use Symfony\Component\Scheduler\Cron\CronRegistry;
use Symfony\Component\Scheduler\Transport\TransportInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronFactoryTest extends TestCase
{
    public function testCronCanBeCreated(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects(self::once())->method('getOptions')->willReturn([]);

        $registry = new CronRegistry();

        $factory = new CronFactory($registry);
        $factory->create('foo', $transport, ['path' => sys_get_temp_dir()]);

        static::assertSame(1, $registry->count());
    }
}
