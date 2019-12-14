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
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Scheduler\Cron\CronGenerator;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CronGeneratorTest extends TestCase
{
    public function testFileCannotBeCreatedWhenItAlreadyExist(): void
    {
        $fs = new Filesystem();
        $fs->mkdir(sprintf('%s/%s', sys_get_temp_dir(), 'app'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $generator = new CronGenerator($fs, $logger);
        $generator->generate('app', sys_get_temp_dir());
    }

    public function testFileCanBeCreated(): void
    {
        $fs = new Filesystem();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $generator = new CronGenerator($fs, $logger);
        $generator->generate('app', sys_get_temp_dir());
    }

    public function testContentCanBeDumped(): void
    {
        $fs = new Filesystem();
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');
        $content = '* * * * * root bin/sh bin/console cache:clear --env test';

        $generator = new CronGenerator($fs, $logger);
        $generator->write($content, 'cron', sys_get_temp_dir());

        static::assertSame(file_get_contents(sys_get_temp_dir().'/cron'), $content);
    }
}
