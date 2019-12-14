<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Google\Tests\Task;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Bridge\Google\Task\Job;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class JobTest extends TestCase
{
    public function testInformationsCanBeReturnedAsArray(): void
    {
        $job = new Job('test');

        static::assertNotEmpty($job->toArray());
        static::assertArrayHasKey('name', $job->toArray());
        static::assertArrayHasKey('schedule', $job->toArray());
        static::assertArrayHasKey('timeZone', $job->toArray());
    }
}
