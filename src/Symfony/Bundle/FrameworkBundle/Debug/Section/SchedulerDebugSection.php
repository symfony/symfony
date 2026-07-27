<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * The "Scheduler" tab of the interactive "debug" command.
 *
 * Reuses the same data source (the schedule providers) as the "debug:scheduler"
 * command, so the detail pane shows the trigger, provider and next run date of
 * the selected recurring message exactly like "debug:scheduler" does.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class SchedulerDebugSection extends AbstractDebugSection
{
    public function __construct(
        private readonly ServiceProviderInterface $schedules,
    ) {
    }

    public function getLabel(): string
    {
        return 'Scheduler';
    }

    public function getShortLabel(): string
    {
        return 'Sched';
    }

    public function describe(DebugItem $item, int $width): string
    {
        [$name, $id] = explode(self::VALUE_SEPARATOR, $item->value, 2);

        if (!$this->schedules->has($name)) {
            return \sprintf('Schedule "%s" does not exist.', $name);
        }

        $schedule = $this->schedules->get($name)->getSchedule();

        $message = null;
        foreach ($schedule->getRecurringMessages() as $recurringMessage) {
            if ($id === $recurringMessage->getId()) {
                $message = $recurringMessage;

                break;
            }
        }

        if (!$message instanceof RecurringMessage) {
            return \sprintf('The selected recurring message no longer exists in schedule "%s".', $name);
        }

        return $this->describeToBuffer(static function (SymfonyStyle $io) use ($name, $schedule, $message): void {
            $io->section($name);

            $date = new \DateTimeImmutable('now');
            if (null !== $checkpoint = self::getStatefulCheckpointTime($schedule, $name)) {
                $date = $checkpoint;
                $io->comment(\sprintf('Schedule "%s" is stateful: next run date computed from stored checkpoint %s.', $name, $date->format('r')));
            }

            $row = self::renderRecurringMessage($message, $date);

            $io->table(
                ['Trigger', 'Provider', 'Next Run'],
                [[$row[0], $row[1], $row[2]?->format('r') ?? '-']],
            );
        });
    }

    protected function buildItems(): array
    {
        $names = array_keys($this->schedules->getProvidedServices());
        $singleSchedule = 1 === \count($names);

        $items = [];
        foreach ($names as $name) {
            if (!$this->schedules->has($name)) {
                continue;
            }

            foreach ($this->schedules->get($name)->getSchedule()->getRecurringMessages() as $message) {
                $row = self::renderRecurringMessage($message, new \DateTimeImmutable('now'));
                $label = \sprintf('%s — %s', $row[0], $row[1]);
                if (!$singleSchedule) {
                    $label = \sprintf('%s (%s)', $label, $name);
                }

                $items[] = new DebugItem('message', $name.self::VALUE_SEPARATOR.$message->getId(), $label, searchText: $name);
            }
        }

        return $items;
    }

    /**
     * Returns a stateful schedule's last-run time, read from its cache without populating it.
     *
     * Mirrors {@see \Symfony\Component\Scheduler\Command\DebugCommand}: a best-effort base date
     * for the displayed next run; anything unexpected falls back to `null` so the detail keeps
     * using `now` instead of crashing.
     */
    private static function getStatefulCheckpointTime(Schedule $schedule, string $name): ?\DateTimeImmutable
    {
        if (!$state = $schedule->getState()) {
            return null;
        }

        $checkpoint = $state->get('scheduler_checkpoint_'.$name, static function (ItemInterface $item, bool &$save) {
            $save = false;

            return null;
        });

        if (!\is_array($checkpoint)) {
            return null;
        }

        return ($checkpoint[0] ?? null) instanceof \DateTimeImmutable ? $checkpoint[0] : null;
    }

    /**
     * @return array{0:string,1:string,2:\DateTimeImmutable|null}
     */
    private static function renderRecurringMessage(RecurringMessage $recurringMessage, \DateTimeImmutable $date): array
    {
        $trigger = $recurringMessage->getTrigger();
        $next = $trigger->getNextRunDate($date);

        $provider = $recurringMessage->getProvider();
        $description = $provider instanceof \Stringable ? (string) $provider : $provider->getId();

        return [(string) $trigger, $description, $next];
    }
}
