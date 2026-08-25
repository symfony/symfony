<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\Profiler\Profile;

class ConsoleProfilerTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        static::bootKernel(['test_case' => 'ConsoleProfiler', 'debug' => true]);
        static::getContainer()->get('public_profiler')->purge();
    }

    public function testProfileCommandDisabledByListener()
    {
        $application = $this->createApplication();
        $this->stopCommandWith(static fn (ConsoleCommandEvent $event) => $event->disableCommand());

        $exitCode = $application->run(new ArrayInput(['command' => 'app:profiled', 'name' => 'Fabien', '--profile' => true]), new NullOutput());

        $this->assertSame(ConsoleCommandEvent::RETURN_CODE_DISABLED, $exitCode);

        $profile = $this->loadProfile();

        $this->assertStringContainsString('app:profiled', $profile->getUrl());
        $this->assertSame(ConsoleCommandEvent::RETURN_CODE_DISABLED, $profile->getStatusCode());
        $this->assertSame('Fabien', $profile->getCollector('command')->getArguments()['name']->getValue());
    }

    public function testProfileCommandStoppedByThrowingListener()
    {
        $application = $this->createApplication();
        $this->stopCommandWith(static fn () => throw new \RuntimeException('Access denied.'));

        try {
            $application->run(new ArrayInput(['command' => 'app:profiled', 'name' => 'Fabien', '--profile' => true]), new NullOutput());
            $this->fail('The listener should have stopped the command.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Access denied.', $e->getMessage());
        }

        $profile = $this->loadProfile();

        $this->assertSame(1, $profile->getStatusCode());
        $this->assertSame('Fabien', $profile->getCollector('command')->getArguments()['name']->getValue());
        $this->assertTrue($profile->getCollector('exception')->hasException());
    }

    public function testProfileCommandThatRuns()
    {
        $application = $this->createApplication();

        $exitCode = $application->run(new ArrayInput(['command' => 'app:profiled', 'name' => 'Fabien', '--profile' => true]), new NullOutput());

        $this->assertSame(Command::SUCCESS, $exitCode);

        $profile = $this->loadProfile();

        $this->assertSame(Command::SUCCESS, $profile->getStatusCode());
        $this->assertSame('Fabien', $profile->getCollector('command')->getArguments()['name']->getValue());
    }

    private function createApplication(): Application
    {
        $command = new Command('app:profiled');
        $command->addArgument('name', InputArgument::REQUIRED);
        $command->setCode(static fn (): int => Command::SUCCESS);

        $application = new Application(static::$kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->addCommand($command);

        return $application;
    }

    private function stopCommandWith(callable $listener): void
    {
        static::getContainer()->get('event_dispatcher')->addListener(ConsoleEvents::COMMAND, $listener);
    }

    private function loadProfile(): Profile
    {
        $profiler = static::getContainer()->get('public_profiler');
        $tokens = $profiler->find('', '', 2, '', '', '');

        $this->assertCount(1, $tokens);

        return $profiler->loadProfile($tokens[0]['token']);
    }
}
