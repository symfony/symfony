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
use Symfony\Component\Scheduler\ScheduleProviderInterface;
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
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> lists schedules and their recurring messages:

                  <info>php %command.full_name%</info>

                Or for a specific schedule only:

                  <info>php %command.full_name% default</info>

                You can also specify a date to use for the next run date:

                  <info>php %command.full_name% --date=2025-10-18</info>

                To also display the terminated recurring messages, use the <info>--all</info> option:

                  <info>php %command.full_name% --all</info>

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

        $date = new \DateTimeImmutable($input->getOption('date'));
        if ('now' !== $input->getOption('date')) {
            $io->comment(\sprintf('All next run dates computed from %s.', $date->format('r')));
        }

        foreach ($names as $name) {
            $io->section($name);

            /** @var ScheduleProviderInterface $schedule */
            $schedule = $this->schedules->get($name);
            if (!$messages = $schedule->getSchedule()->getRecurringMessages()) {
                $io->warning(\sprintf('No recurring messages found for schedule "%s".', $name));

                continue;
            }
            $io->table(
                ['Trigger', 'Provider', 'Next Run'],
                array_filter(array_map(self::renderRecurringMessage(...), $messages, array_fill(0, \count($messages), $date), array_fill(0, \count($messages), $input->getOption('all')))),
            );
        }

        return 0;
    }

    /**
     * @return array{0:string,1:string,2:string}|null
     */
    private static function renderRecurringMessage(RecurringMessage $recurringMessage, \DateTimeImmutable $date, bool $all): ?array
    {
        $trigger = $recurringMessage->getTrigger();

        $next = $trigger->getNextRunDate($date)?->format('r') ?? '-';
        if ('-' === $next && !$all) {
            return null;
        }

        $provider = $recurringMessage->getProvider();
        $description = $provider instanceof \Stringable ? (string) $provider : $provider->getId();

        return [(string) $trigger, $description, $next];
    }
}
