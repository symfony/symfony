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
use Symfony\Component\Scheduler\Cron\Cron;
use Symfony\Component\Scheduler\Exception\LogicException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronTest extends TestCase
{
    public function testCronCannotBeGeneratedWithoutCompleteOptions(): void
    {
        static::expectException(LogicException::class);
        new Cron('foo');
    }

    public function testCronCanBeGenerated(): void
    {
        $cron = new Cron('foo', ['path' => '/srv/app']);

        static::assertSame('foo', $cron->getName());
        static::assertInstanceOf(\DateTimeInterface::class, $cron->getGenerationDate());
        static::assertSame('* * * * * cd /srv/app && php bin/console scheduler:run foo >> /dev/null 2>&1', $cron->getExpression());
    }
}
