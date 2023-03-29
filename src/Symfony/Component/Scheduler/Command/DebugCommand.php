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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

use function Symfony\Component\Clock\now;

/**
 * Command to list/debug schedules.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @experimental
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
            ->addArgument('schedule', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, sprintf('The schedule name (one of "%s")', implode('", "', $this->scheduleNames)), null, $this->scheduleNames)
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> lists schedules and their recurring messages:

                  <info>php %command.full_name%</info>

                Or for a specific schedule only:

                  <info>php %command.full_name% default</info>

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

            return self::FAILURE;
        }

        foreach ($names as $name) {
            $io->section($name);

            /** @var ScheduleProviderInterface $schedule */
            $schedule = $this->schedules->get($name);
            if (!$messages = $schedule->getSchedule()->getRecurringMessages()) {
                $io->warning(sprintf('No recurring messages found for schedule "%s".', $name));

                continue;
            }
            $io->table(
                ['Message', 'Trigger', 'Next Run'],
                array_map(self::renderRecurringMessage(...), $messages),
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function renderRecurringMessage(RecurringMessage $recurringMessage): array
    {
        $message = $recurringMessage->getMessage();
        $trigger = $recurringMessage->getTrigger();

        if ($message instanceof Envelope) {
            $message = $message->getMessage();
        }

        $messageName = (new \ReflectionClass($message))->getShortName();
        $triggerName = (new \ReflectionClass($trigger))->getShortName();

        if ($message instanceof \Stringable) {
            $messageName .= ": {$message}";
        }

        if ($trigger instanceof \Stringable) {
            $triggerName .= ": {$trigger}";
        }

        return [
            $messageName,
            $triggerName,
            $recurringMessage->getTrigger()->getNextRunDate(now())->format(\DateTimeInterface::ATOM),
        ];
    }
}
