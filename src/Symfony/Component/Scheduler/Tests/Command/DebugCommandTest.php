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
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Command\DebugCommand;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\CallbackTrigger;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Max Beckers <beckers.maximilian@gmail.com>
 */
class DebugCommandTest extends TestCase
{
    public function testExecuteWithoutSchedules()
    {
        $schedules = $this->createMock(ServiceProviderInterface::class);
        $schedules
            ->expects($this->once())
            ->method('getProvidedServices')
            ->willReturn([])
        ;

        $command = new DebugCommand($schedules);
        $tester = new CommandTester($command);

        $tester->execute([], ['decorated' => false]);

        $filler = str_repeat(' ', 92);
        $this->assertSame("\nScheduler\n=========\n\n [ERROR] No schedules found.{$filler}\n\n", $tester->getDisplay(true));
    }

    public function testExecuteWithScheduleWithoutTriggerShowingNoNextRun()
    {
        $schedule = new Schedule();
        $schedule->add(RecurringMessage::trigger(new CallbackTrigger(fn () => null, 'test'), new \stdClass()));

        $schedules = $this->createMock(ServiceProviderInterface::class);
        $schedules
            ->expects($this->once())
            ->method('getProvidedServices')
            ->willReturn(['schedule_name' => $schedule])
        ;
        $schedules
            ->expects($this->once())
            ->method('get')
            ->willReturn($schedule)
        ;

        $command = new DebugCommand($schedules);
        $tester = new CommandTester($command);

        $tester->execute([], ['decorated' => false]);

        $this->assertSame("\n".
            "Scheduler\n".
            "=========\n".
            "\n".
            "schedule_name\n".
            "-------------\n".
            "\n".
            " ---------- ----------------------- ---------- \n".
            "  Message    Trigger                 Next Run  \n".
            " ---------- ----------------------- ---------- \n".
            "  stdClass   CallbackTrigger: test   -         \n".
            " ---------- ----------------------- ---------- \n".
            "\n", $tester->getDisplay(true));
    }

    public function testExecuteWithSchedule()
    {
        $schedule = new Schedule();
        $schedule->add(RecurringMessage::every('first day of next month', new \stdClass()));

        $schedules = $this->createMock(ServiceProviderInterface::class);
        $schedules
            ->expects($this->once())
            ->method('getProvidedServices')
            ->willReturn(['schedule_name' => $schedule])
        ;
        $schedules
            ->expects($this->once())
            ->method('get')
            ->willReturn($schedule)
        ;

        $command = new DebugCommand($schedules);
        $tester = new CommandTester($command);

        $tester->execute([], ['decorated' => false]);

        $this->assertMatchesRegularExpression("/\n".
            "Scheduler\n".
            "=========\n".
            "\n".
            "schedule_name\n".
            "-------------\n".
            "\n".
            " ---------- -------------------------------------------------- --------------------------- \n".
            "  Message    Trigger                                            Next Run                   \n".
            " ---------- -------------------------------------------------- --------------------------- \n".
            "  stdClass   PeriodicalTrigger: every first day of next month   \d{4}-[01]\d-[0-3]\dT[0-2]\d:[0-5]\d:[0-5]\d([+-][0-2]\d:[0-5]\d|Z)  \n".
            " ---------- -------------------------------------------------- --------------------------- \n".
            "\n/", $tester->getDisplay(true));
    }
}
