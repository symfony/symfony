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
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\BarMessage;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\Messenger\FooMessage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group functional
 */
class SchedulerDebugCommandTest extends AbstractWebTestCase
{
    private $application;

    protected function setUp(): void
    {
        $kernel = static::createKernel(['test_case' => 'SchedulerDebug', 'root_config' => 'config.yml']);
        $this->application = new Application($kernel);
    }

    public function testRunWithNoArguments()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Information for Scheduler "default"', $tester->getDisplay());
        $this->assertStringContainsString(BarMessage::class, $tester->getDisplay());
        $this->assertStringContainsString(FooMessage::class, $tester->getDisplay());
    }

    public function testRunWithNameArgument()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'default']);

        $this->assertSame(Command::SUCCESS, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Information for Scheduler "default"', $tester->getDisplay());
        $this->assertStringContainsString(BarMessage::class, $tester->getDisplay());
        $this->assertStringContainsString(FooMessage::class, $tester->getDisplay());
    }

    public function testRunWithWrongNameArgument()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'foo']);

        $this->assertSame(Command::INVALID, $ret, 'Returns 2 in case of invalid input');
        $this->assertStringContainsString('Available schedulers: default', $tester->getDisplay());
    }

    public function testRunWithNameAndMessageArgument()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['name' => 'default', 'message' => 'FooMessage']);

        $this->assertSame(Command::SUCCESS, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Information for Scheduler "default"', $tester->getDisplay());
        $this->assertStringNotContainsString(BarMessage::class, $tester->getDisplay());
        $this->assertStringContainsString(FooMessage::class, $tester->getDisplay());
    }

    public function testRunWithShowLockOption()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['--show-lock' => true]);

        $this->assertSame(Command::SUCCESS, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Information for Scheduler "default"', $tester->getDisplay());
        $this->assertStringContainsString('Displaying only Scheduler Lock information', $tester->getDisplay());
        $this->assertStringNotContainsString(BarMessage::class, $tester->getDisplay());
        $this->assertStringNotContainsString(FooMessage::class, $tester->getDisplay());
    }

    public function testRunWithShowStateOption()
    {
        $tester = $this->createCommandTester();
        $ret = $tester->execute(['--show-state' => true]);

        $this->assertSame(Command::SUCCESS, $ret, 'Returns 0 in case of success');
        $this->assertStringContainsString('Information for Scheduler "default"', $tester->getDisplay());
        $this->assertStringContainsString('Displaying only Scheduler Cache information', $tester->getDisplay());
        $this->assertStringNotContainsString(BarMessage::class, $tester->getDisplay());
        $this->assertStringNotContainsString(FooMessage::class, $tester->getDisplay());
    }

    private function createCommandTester(): CommandTester
    {
        $command = $this->application->find('debug:scheduler');

        return new CommandTester($command);
    }
}
