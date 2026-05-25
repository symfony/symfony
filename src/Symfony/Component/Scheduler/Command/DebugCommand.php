<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Command to list/debug schedules.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
#[AsCommand(name: 'debug:scheduler', description: 'List schedules and their recurring messages')]
final class DebugCommand extends Command
{
    private array $scheduleNames;

    public function __construct(private ServiceProviderInterface $schedules)
    {
        $this->scheduleNames = array_keys($this->schedules->getProvidedServices());

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('schedule', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, \sprintf('The schedule name (one of "%s")', implode('", "', $this->scheduleNames)), null, $this->scheduleNames)
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'The date to use for the next run date', 'now')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Display all recurring messages, including the terminated ones')
            ->addOption('sort', null, InputOption::VALUE_NONE, 'Sort recurring messages by next run date')
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> lists schedules and their recurring messages:

                  <info>php %command.full_name%</info>

                Or for a specific schedule only:

                  <info>php %command.full_name% default</info>

                You can also specify a date to use for the next run date:

                  <info>php %command.full_name% --date=2025-10-18</info>

                To also display the terminated recurring messages, use the <info>--all</info> option:

                  <info>php %command.full_name% --all</info>

                To sort the displayed recurring messages by their next run date, use the <info>--sort</info> option:

                  <info>php %command.full_name% --sort</info>

                When combined with <info>--all</info>, rows with no next run date are listed first.

                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Scheduler');

        if (!$names = $input->getArgument('schedule') ?: $this->scheduleNames) {
            $io->error('No schedules found.');

            return 2;
        }

        $dateOption = $input->getOption('date');
        $date = new \DateTimeImmutable($dateOption);
        if ('now' !== $dateOption) {
            $io->comment(\sprintf('All next run dates computed from %s.', $date->format('r')));
        }

        foreach ($names as $name) {
            $io->section($name);

            /** @var Schedule $schedule */
            $schedule = $this->schedules->get($name)->getSchedule();
            if (!$messages = $schedule->getRecurringMessages()) {
                $io->warning(\sprintf('No recurring messages found for schedule "%s".', $name));

                continue;
            }
            $effectiveDate = $date;
            if ('now' === $dateOption && null !== $checkpoint = self::getStatefulCheckpointTime($schedule, $name)) {
                $effectiveDate = $checkpoint;
                $io->comment(\sprintf('Schedule "%s" is stateful: next run dates computed from stored checkpoint %s.', $name, $effectiveDate->format('r')));
            }

            $recurringMessages = array_filter(array_map(self::renderRecurringMessage(...), $messages, array_fill(0, \count($messages), $effectiveDate), array_fill(0, \count($messages), $input->getOption('all'))));

            if ($input->getOption('sort')) {
                usort($recurringMessages, static fn (array $a, array $b): int => $a[2] <=> $b[2]);
            }

            $io->table(
                ['Trigger', 'Provider', 'Next Run'],
                array_map(static fn (array $row): array => [$row[0], $row[1], $row[2]?->format('r') ?? '-'], $recurringMessages),
            );
        }

        return 0;
    }

    /**
     * Returns a stateful schedule's last-run time, read from its cache without populating it.
     *
     * This mirrors the worker's checkpoint storage (key and `[\DateTimeImmutable $time, int $index,
     * \DateTimeImmutable $from]` tuple, owned by {@see \Symfony\Component\Scheduler\Generator\Checkpoint}).
     * It is a best-effort base date for the displayed next runs, not an exact replay of the worker;
     * anything unexpected falls back to `null` so `debug:scheduler` keeps using `now` instead of crashing.
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
     * @return array{0:string,1:string,2:\DateTimeImmutable|null}|null
     */
    private static function renderRecurringMessage(RecurringMessage $recurringMessage, \DateTimeImmutable $date, bool $all): ?array
    {
        $trigger = $recurringMessage->getTrigger();

        $next = $trigger->getNextRunDate($date);
        if (null === $next && !$all) {
            return null;
        }

        $provider = $recurringMessage->getProvider();
        $description = $provider instanceof \Stringable ? (string) $provider : $provider->getId();

        return [(string) $trigger, $description, $next];
    }
}
