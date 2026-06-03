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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Scheduler\Command\DebugCommand;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\CallbackTrigger;
use Symfony\Contracts\Cache\ItemInterface;
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

        $display = preg_replace('/ +$/m', '', $tester->getDisplay(true));
        $this->assertSame("\nScheduler\n=========\n\n [ERROR] No schedules found.\n\n", $display);
    }

    public function testExecuteWithScheduleWithoutTriggerDoesNotDisplayMessage()
    {
        $schedule = new Schedule();
        $schedule->add(RecurringMessage::trigger(new CallbackTrigger(static fn () => null, 'test'), new \stdClass()));

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
            " --------- ---------- ---------- \n".
            "  Trigger   Provider   Next Run  \n".
            " --------- ---------- ---------- \n".
            "\n", $tester->getDisplay(true));
    }

    public function testExecuteWithScheduleWithoutTriggerShowingNoNextRunWithAllOption()
    {
        $schedule = new Schedule();
        $schedule->add(RecurringMessage::trigger(new CallbackTrigger(static fn () => null, 'test'), new \stdClass()));

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

        $tester->execute(['--all' => true], ['decorated' => false]);

        $this->assertSame("\n".
            "Scheduler\n".
            "=========\n".
            "\n".
            "schedule_name\n".
            "-------------\n".
            "\n".
            " --------- ---------- ---------- \n".
            "  Trigger   Provider   Next Run  \n".
            " --------- ---------- ---------- \n".
            "  test      stdClass   -         \n".
            " --------- ---------- ---------- \n".
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
            " ------------------------------- ---------- --------------------------------- \n".
            "  Trigger                         Provider   Next Run                         \n".
            " ------------------------------- ---------- --------------------------------- \n".
            "  every first day of next month   stdClass   \w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}  \n".
            " ------------------------------- ---------- --------------------------------- \n".
            "\n/", $tester->getDisplay(true));
    }

    #[DataProvider('provideSorting')]
    public function testExecuteWithScheduleAndSortOption(bool $sorted, string $expected)
    {
        $schedule = new Schedule();
        $schedule
            ->add(RecurringMessage::every('3 minutes', new \stdClass()))
            ->add(RecurringMessage::every('1 minute', new \stdClass()))
            ->add(RecurringMessage::every('2 minutes', new \stdClass()))
        ;

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

        $tester->execute(['--sort' => $sorted], ['decorated' => false]);

        $this->assertMatchesRegularExpression($expected, $tester->getDisplay(true));
    }

    public static function provideSorting(): iterable
    {
        yield 'Not sorted results' => [
            false,
            "/\n".
            "Scheduler\n".
            "=========\n".
            "\n".
            "schedule_name\n".
            "-------------\n".
            "\n".
            " ----------------- ---------- --------------------------------- \n".
            "  Trigger           Provider   Next Run                         \n".
            " ----------------- ---------- --------------------------------- \n".
            "  every 3 minutes   stdClass   \w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}  \n".
            "  every 1 minute    stdClass   \w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}  \n".
            "  every 2 minutes   stdClass   \w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}  \n".
            " ----------------- ---------- --------------------------------- \n".
            "\n/",
        ];

        yield 'Sorted results' => [
            true,
            "/\n".
            "Scheduler\n".
            "=========\n".
            "\n".
            "schedule_name\n".
            "-------------\n".
            "\n".
            " ----------------- ---------- --------------------------------- \n".
            "  Trigger           Provider   Next Run                         \n".
            " ----------------- ---------- --------------------------------- \n".
            "  every 1 minute    stdClass   \w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}  \n".
            "  every 2 minutes   stdClass   \w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}  \n".
            "  every 3 minutes   stdClass   \w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}  \n".
            " ----------------- ---------- --------------------------------- \n".
            "\n/",
        ];
    }

    public function testExecuteWithSortAndAllListsNullNextRunFirst()
    {
        $schedule = new Schedule();
        $schedule
            ->add(RecurringMessage::every('2 minutes', new \stdClass()))
            ->add(RecurringMessage::trigger(new CallbackTrigger(static fn () => null, 'terminated'), new \stdClass()))
            ->add(RecurringMessage::every('1 minute', new \stdClass()))
        ;

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

        $tester->execute(['--sort' => true, '--all' => true], ['decorated' => false]);

        $this->assertMatchesRegularExpression("/\n".
            "Scheduler\n".
            "=========\n".
            "\n".
            "schedule_name\n".
            "-------------\n".
            "\n".
            " ----------------- ---------- --------------------------------- \n".
            "  Trigger           Provider   Next Run                         \n".
            " ----------------- ---------- --------------------------------- \n".
            "  terminated        stdClass   -                                \n".
            "  every 1 minute    stdClass   \w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}  \n".
            "  every 2 minutes   stdClass   \w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}  \n".
            " ----------------- ---------- --------------------------------- \n".
            "\n/", $tester->getDisplay(true));
    }

    public function testExecuteWithSortPreservesInsertionOrderOnTies()
    {
        $schedule = new Schedule();
        $schedule
            ->add(RecurringMessage::trigger(new CallbackTrigger(static fn (\DateTimeImmutable $run) => $run->modify('+1 minute'), 'first'), new \stdClass()))
            ->add(RecurringMessage::trigger(new CallbackTrigger(static fn (\DateTimeImmutable $run) => $run->modify('+1 minute'), 'second'), new \stdClass()))
            ->add(RecurringMessage::trigger(new CallbackTrigger(static fn (\DateTimeImmutable $run) => $run->modify('+1 minute'), 'third'), new \stdClass()))
        ;

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

        $tester->execute(['--sort' => true], ['decorated' => false]);

        $display = $tester->getDisplay(true);
        $firstPos = strpos($display, 'first');
        $secondPos = strpos($display, 'second');
        $thirdPos = strpos($display, 'third');

        $this->assertNotFalse($firstPos);
        $this->assertNotFalse($secondPos);
        $this->assertNotFalse($thirdPos);
        $this->assertLessThan($secondPos, $firstPos);
        $this->assertLessThan($thirdPos, $secondPos);
    }

    public function testExecuteWithStatefulScheduleUsesStoredCheckpoint()
    {
        $cache = new ArrayAdapter();
        $checkpointTime = new \DateTimeImmutable('2024-01-15 10:00:00 UTC');
        $cache->get('scheduler_checkpoint_schedule_name', static fn (ItemInterface $item) => [$checkpointTime, 0, $checkpointTime]);

        $schedule = (new Schedule())->stateful($cache);
        $schedule->add(RecurringMessage::every('1 hour', new \stdClass()));

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

        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('stateful', $display);
        // "every 1 hour" seeded with checkpoint 2024-01-15 10:00:00 UTC ⇒ next run 11:00:00 UTC, not "now + 1h"
        $this->assertStringContainsString('Mon, 15 Jan 2024 11:00:00 +0000', $display);
    }

    public function testExecuteWithStatefulScheduleWithoutCheckpointFallsBackToNow()
    {
        // Stateful schedule but no checkpoint stored yet ⇒ command must not mention "stateful" and use "now".
        $cache = new ArrayAdapter();

        $schedule = (new Schedule())->stateful($cache);
        $schedule->add(RecurringMessage::every('1 hour', new \stdClass()));

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

        $display = $tester->getDisplay(true);
        $this->assertStringNotContainsString('stateful', $display);
        $this->assertMatchesRegularExpression('/every 1 hour\s+stdClass\s+\w{3}, \d{1,2} \w{3} \d{4} \d{2}:\d{2}:\d{2} (\+|-)\d{4}/', $display);
    }
}
