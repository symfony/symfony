<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Kubernetes\Tests\Task;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Task\CronJob;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronJobTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $job = new CronJob('foo', '1.18');

        static::assertSame('foo', $job->getName());
        static::assertSame('1.18', $job->get('api_version'));
    }
}
