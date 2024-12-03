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

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\SingleMessageReceiver;
use Symfony\Component\Messenger\Worker;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * Command to run a task immediately
 *
 * @author valtzu <valtzu@gmail.com>
 */
#[AsCommand(name: 'scheduler:task:run', description: 'Run single scheduler task now')]
final class RunTaskCommand extends Command
{
    private array $scheduleNames;

    public function __construct(
        private readonly ServiceProviderInterface $schedules,
        private readonly MessageBusInterface $messageBus,
        private readonly ContainerInterface $receiverLocator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->scheduleNames = array_keys($this->schedules->getProvidedServices());

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('task', InputArgument::REQUIRED, 'The ID of the task to run')
            ->addOption('schedule', 's', InputOption::VALUE_REQUIRED, \sprintf('The schedule name (one of "%s")', implode('", "', $this->scheduleNames)), 'default', $this->scheduleNames)
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> runs or dispatches a single scheduled task, ignoring its schedule:

                  <info>php %command.full_name% example_task_id</info>

                Or for a specific schedule:

                  <info>php %command.full_name% --schedule=default example_task_id</info>

                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getOption('schedule');

        if (!$this->schedules->has($name)) {
            $io->error(\sprintf('Schedule %s not found.', $name));

            return 2;
        }

        /** @var ScheduleProviderInterface $schedule */
        $schedule = $this->schedules->get($name);
        if (!$messages = $schedule->getSchedule()->getRecurringMessages()) {
            $io->warning(\sprintf('No recurring messages found for schedule "%s".', $name));

            return 3;
        }

        $id = $input->getArgument('task');
        $found = false;
        foreach ($messages as $recurringMessage) {
            if ($recurringMessage->getId() !== $id) {
                continue;
            }

            $provider = $recurringMessage->getProvider();
            $description = ($provider instanceof \Stringable ? (string)$provider : $provider->getId());
            $context = new MessageContext($name, $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
            $io->info(\sprintf('Running task "%s"', $description));

            foreach ($provider->getMessages($context) as $message) {
                $found = true;
                $singleReceiver = new SingleMessageReceiver($this->receiverLocator->get('scheduler_' . $name), Envelope::wrap($message));
                $worker = new Worker(
                    [$name => $singleReceiver],
                    $this->messageBus,
                    $this->eventDispatcher,
                    $this->logger,
                );

                $this->eventDispatcher->addListener(WorkerMessageReceivedEvent::class, $listener = $worker->stop(...));
                try {
                    $worker->run();
                } finally {
                    $this->eventDispatcher->removeListener(WorkerMessageReceivedEvent::class, $listener);
                }
            }
        }

        if (!$found) {
            $io->error(\sprintf('No task with ID "%s" found in schedule "%s".', $id, $name));
            return 3;
        }

        $io->success('Task finished successfully.');

        return 0;
    }
}
