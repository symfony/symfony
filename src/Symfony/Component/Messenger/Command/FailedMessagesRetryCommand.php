<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\SingleMessageReceiver;
use Symfony\Component\Messenger\Worker;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class FailedMessagesRetryCommand extends AbstractFailedMessagesCommand
{
    protected static $defaultName = 'messenger:failed:retry';

    private $eventDispatcher;
    private $messageBus;
    private $logger;

    public function __construct(string $receiverName, ReceiverInterface $receiver, MessageBusInterface $messageBus, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger = null)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->messageBus = $messageBus;
        $this->logger = $logger;

        parent::__construct($receiverName, $receiver);
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('id', InputArgument::IS_ARRAY, 'Specific message id(s) to retry'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Force action without confirmation'),
            ])
            ->setDescription('Retry one or more messages from the failure transport.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> retries message in the failure transport.

    <info>php %command.full_name%</info>

The command will interactively ask if each message should be retried
or discarded.

Some transports support retrying a specific message id, which comes
from the <info>messenger:failed:show</info> command.

    <info>php %command.full_name% {id}</info>

Or pass multiple ids at once to process multiple messages:

<info>php %command.full_name% {id1} {id2} {id3}</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $io->comment('Quit this command with CONTROL-C.');
        if (!$output->isVeryVerbose()) {
            $io->comment('Re-run the command with a -vv option to see logs about consumed messages.');
        }

        $receiver = $this->getReceiver();
        $this->printPendingMessagesMessage($receiver, $io);

        $io->writeln(sprintf('To retry all the messages, run <comment>messenger:consume %s</comment>', $this->getReceiverName()));

        $shouldForce = $input->getOption('force');
        $ids = $input->getArgument('id');
        if (0 === \count($ids)) {
            if (!$input->isInteractive()) {
                throw new RuntimeException('Message id must be passed when in non-interactive mode.');
            }

            $this->runInteractive($io, $shouldForce);

            return 0;
        }

        $this->retrySpecificIds($ids, $io, $shouldForce);
        $io->success('All done!');

        return 0;
    }

    private function runInteractive(SymfonyStyle $io, bool $shouldForce)
    {
        $receiver = $this->getReceiver();
        $count = 0;
        if ($receiver instanceof ListableReceiverInterface) {
            // for listable receivers, find the messages one-by-one
            // this avoids using get(), which for some less-robust
            // transports (like Doctrine), will cause the message
            // to be temporarily "acked", even if the user aborts
            // handling the message
            while (true) {
                $ids = [];
                foreach ($receiver->all(1) as $envelope) {
                    ++$count;

                    $id = $this->getMessageId($envelope);
                    if (null === $id) {
                        throw new LogicException(sprintf('The "%s" receiver is able to list messages by id but the envelope is missing the TransportMessageIdStamp stamp.', $this->getReceiverName()));
                    }
                    $ids[] = $id;
                }

                // break the loop if all messages are consumed
                if (0 === \count($ids)) {
                    break;
                }

                $this->retrySpecificIds($ids, $io, $shouldForce);
            }
        } else {
            // get() and ask messages one-by-one
            $count = $this->runWorker($this->getReceiver(), $io, $shouldForce);
        }

        // avoid success message if nothing was processed
        if (1 <= $count) {
            $io->success('All failed messages have been handled or removed!');
        }
    }

    private function runWorker(ReceiverInterface $receiver, SymfonyStyle $io, bool $shouldForce): int
    {
        $count = 0;
        $listener = function (WorkerMessageReceivedEvent $messageReceivedEvent) use ($io, $receiver, $shouldForce, &$count) {
            ++$count;
            $envelope = $messageReceivedEvent->getEnvelope();

            $this->displaySingleMessage($envelope, $io);

            $shouldHandle = $shouldForce || $io->confirm('Do you want to retry (yes) or delete this message (no)?');

            if ($shouldHandle) {
                return;
            }

            $messageReceivedEvent->shouldHandle(false);
            $receiver->reject($envelope);
        };
        $this->eventDispatcher->addListener(WorkerMessageReceivedEvent::class, $listener);

        $worker = new Worker(
            [$this->getReceiverName() => $receiver],
            $this->messageBus,
            $this->eventDispatcher,
            $this->logger
        );

        try {
            $worker->run();
        } finally {
            $this->eventDispatcher->removeListener(WorkerMessageReceivedEvent::class, $listener);
        }

        return $count;
    }

    private function retrySpecificIds(array $ids, SymfonyStyle $io, bool $shouldForce)
    {
        $receiver = $this->getReceiver();

        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(sprintf('The "%s" receiver does not support retrying messages by id.', $this->getReceiverName()));
        }

        foreach ($ids as $id) {
            $envelope = $receiver->find($id);
            if (null === $envelope) {
                throw new RuntimeException(sprintf('The message "%s" was not found.', $id));
            }

            $singleReceiver = new SingleMessageReceiver($receiver, $envelope);
            $this->runWorker($singleReceiver, $io, $shouldForce);
        }
    }
}
