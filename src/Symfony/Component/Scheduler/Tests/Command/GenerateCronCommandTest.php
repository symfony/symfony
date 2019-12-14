<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Command;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Scheduler\Command\GenerateCronCommand;
use Symfony\Component\Scheduler\Cron\Cron;
use Symfony\Component\Scheduler\Cron\CronGenerator;
use Symfony\Component\Scheduler\Cron\CronRegistry;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class GenerateCronCommandTest extends TestCase
{
    public function testCommandIsConfigured(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $logger = $this->createMock(LoggerInterface::class);

        $generator = new CronGenerator($fs, $logger);
        $registry = new CronRegistry();

        $command = new GenerateCronCommand($generator, $registry);

        static::assertSame('scheduler:generate', $command->getName());
        static::assertSame('Generate the cron file for each scheduler', $command->getDescription());
        static::assertNotNull($command->getDefinition());
    }

    public function testCommandCannotGenerateOnEmptySchedulers(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $logger = $this->createMock(LoggerInterface::class);

        $generator = new CronGenerator($fs, $logger);
        $registry = new CronRegistry();

        $command = new GenerateCronCommand($generator, $registry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:generate'));
        $tester->execute([]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('No cron file found, please be sure that at least a scheduler is defined', $tester->getDisplay());
    }

    public function testCommandCannotGenerateOnFsException(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $fs->expects(self::once())->method('mkdir')->will(self::throwException(new IOException('The directory is not valid')));

        $logger = $this->createMock(LoggerInterface::class);

        $generator = new CronGenerator($fs, $logger);
        $registry = new CronRegistry();
        $registry->register('foo', new Cron('foo', ['path' => sys_get_temp_dir()]));

        $command = new GenerateCronCommand($generator, $registry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:generate'));
        $tester->execute([
            'schedulers' => ['foo'],
        ]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('An error occurred: The directory is not valid', $tester->getDisplay());
    }

    public function testCommandCanGenerateCronFiles(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $logger = $this->createMock(LoggerInterface::class);

        $generator = new CronGenerator($fs, $logger);
        $registry = new CronRegistry();
        $registry->register('foo', new Cron('foo', ['path' => sys_get_temp_dir()]));

        $command = new GenerateCronCommand($generator, $registry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:generate'));
        $tester->execute([]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('Cron files have been generated for schedulers', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Directory', $tester->getDisplay());
    }

    public function testCommandCanGenerateCronFilesWithSpecificDirectory(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $logger = $this->createMock(LoggerInterface::class);

        $generator = new CronGenerator($fs, $logger);
        $registry = new CronRegistry();
        $registry->register('foo', new Cron('foo', ['path' => sys_get_temp_dir()]));

        $command = new GenerateCronCommand($generator, $registry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:generate'));
        $tester->execute([
            '--directory' => '/srv/app',
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('Cron files have been generated for schedulers at "/srv/app"', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Directory', $tester->getDisplay());
    }

    public function testCommandCanBeGeneratedWithSpecificSchedulers(): void
    {
        $fs = $this->createMock(Filesystem::class);
        $logger = $this->createMock(LoggerInterface::class);

        $generator = new CronGenerator($fs, $logger);
        $registry = new CronRegistry();
        $registry->register('foo', new Cron('foo', ['path' => sys_get_temp_dir()]));

        $command = new GenerateCronCommand($generator, $registry);

        $application = new Application();
        $application->add($command);
        $tester = new CommandTester($application->get('scheduler:generate'));
        $tester->execute([
            'schedulers' => ['foo'],
        ]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('Cron files have been generated for schedulers', $tester->getDisplay());
        static::assertStringContainsString('Name', $tester->getDisplay());
        static::assertStringContainsString('Directory', $tester->getDisplay());
    }
}
