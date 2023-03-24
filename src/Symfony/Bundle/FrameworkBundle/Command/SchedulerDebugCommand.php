<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Scheduler\Schedule;

/**
 * A console command for retrieving information about schedulers.
 *
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @final
 */
#[AsCommand(name: 'debug:scheduler', description: 'Display current schedules for an application')]
class SchedulerDebugCommand extends Command
{
    public function __construct(
        private readonly ServiceLocator $scheduleProviders
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'A scheduler name (uses `default` if none provided)', 'default'),
                new InputArgument('message', InputArgument::OPTIONAL, 'A class name to filter on'),
                new InputOption('from', null, InputOption::VALUE_REQUIRED, 'When the next run date will be calculated from (use \DateTime::ATOM format)', 'now'),
                new InputOption('from-format', null, InputOption::VALUE_REQUIRED, 'Format to use for the from option', \DateTimeInterface::ATOM),
                new InputOption('from-timezone', null, InputOption::VALUE_REQUIRED, 'Timezone to use in combination with the from option', 'UTC'),
                new InputOption('show-lock', null, InputOption::VALUE_NONE, 'Show used lock'),
                new InputOption('show-state', null, InputOption::VALUE_NONE, 'Show used state'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> displays the configured schedules:

  <info>php %command.full_name%</info>

EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $errorIo = $io->getErrorStyle();
        $dateTimeZone = new \DateTimeZone($input->getOption('from-timezone'));
        $dateFrom = 'now' === $input->getOption('from')
            ? new \DateTimeImmutable(timezone: $dateTimeZone)
            : \DateTimeImmutable::createFromFormat($input->getOption('from-format'), $input->getOption('from'), $dateTimeZone);

        $askedScheduler = $input->getArgument('name');
        /** @var string[] $availableSchedulers */
        $availableSchedulers = array_keys($this->scheduleProviders->getProvidedServices());

        if (!\in_array($askedScheduler, $availableSchedulers)) {
            $errorIo->error(sprintf('The "%s" scheduler could not be found. Available schedulers: %s.', $askedScheduler, implode(', ', $availableSchedulers)));

            return Command::INVALID;
        }

        $scheduler = $this->scheduleProviders->get($askedScheduler);
        /** @var Schedule $schedule */
        $schedule = $scheduler->getSchedule();

        $io->title(sprintf('Information for Scheduler "<info>%s</info>"', $askedScheduler));

        if ($input->getOption('show-lock')) {
            $this->showLock($io, $schedule);
        } elseif ($input->getOption('show-state')) {
            $this->showState($io, $schedule);
        } else {
            $this->showMessages($io, $schedule, $dateFrom, $dateTimeZone, $input->getArgument('message'));
        }

        return Command::SUCCESS;
    }

    private function showMessages(SymfonyStyle $io, Schedule $schedule, \DateTimeImmutable $dateFrom, \DateTimeZone $dateTimeZone, string $classFilter = null): void
    {
        if ($filtered = \is_string($classFilter)) {
            $io->comment(sprintf('Displaying only \'%s\' messages', $classFilter));
        }

        $messages = [];
        foreach ($schedule->getRecurringMessages() as $recurringMessage) {
            if ($filtered && !str_contains($recurringMessage->getMessage()::class, $classFilter)) {
                continue;
            }

            $nextRunDate = $recurringMessage->getTrigger()->getNextRunDate($dateFrom);
            $nextRunDate = $nextRunDate->setTimezone($dateTimeZone);

            $messages[] = [
                $recurringMessage->getMessage()::class,
                $recurringMessage->getTrigger()::class,
                $nextRunDate->format(\DateTimeInterface::ATOM),
            ];
        }

        $io->table(['Message', 'Trigger type', 'Next run date'], $messages);
    }

    private function showLock(SymfonyStyle $io, Schedule $schedule): void
    {
        $io->comment('Displaying only Scheduler Lock information');

        if (null === $schedule->getLock()) {
            $io->note('No lock found on given Scheduler');

            return;
        }

        $io->text(sprintf('Using "<info>%s</info>" lock', $schedule->getLock()::class));
    }

    private function showState(SymfonyStyle $io, Schedule $schedule): void
    {
        $io->comment('Displaying only Scheduler Cache information');

        if (null === $schedule->getState()) {
            $io->note('No state found on given Scheduler');

            return;
        }

        $io->text(sprintf('Using "<info>%s</info>" cache adapter', $schedule->getState()::class));
    }
}
