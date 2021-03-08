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
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\MessageDecodingFailedStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\SingleMessageReceiver;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Worker;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class FailedMessagesRetryCommand extends AbstractFailedMessagesCommand
{
    protected static $defaultName = 'messenger:failed:retry';
    protected static $defaultDescription = 'Retry one or more messages from the failure transport';

    private $eventDispatcher;
    private $messageBus;
    private $logger;
    private $phpSerializer;

    public function __construct(string $receiverName, ReceiverInterface $receiver, MessageBusInterface $messageBus, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger = null, ?PhpSerializer $phpSerializer = null)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->messageBus = $messageBus;
        $this->logger = $logger;
        $this->phpSerializer = $phpSerializer;

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
            ->setDescription(self::$defaultDescription)
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
                $envelopes = [];

                try {
                    $this->phpSerializer && $this->phpSerializer->enableClassNotFoundCreation();

                    foreach ($receiver->all(1) as $envelope) {
                        ++$count;

                        $envelopes[] = $envelope;
                    }
                } finally {
                    $this->phpSerializer && $this->phpSerializer->enableClassNotFoundCreation(false);
                }

                // break the loop if all messages are consumed
                if (0 === \count($envelopes)) {
                    break;
                }

                $this->retrySpecificEnvelop($envelopes, $io, $shouldForce);
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

            if ($envelope->last(MessageDecodingFailedStamp::class)) {
                throw new \RuntimeException(sprintf('This message with id "%s" could not decoded. It can only be shown or removed.', $this->getMessageId($envelope) ?? 'NULL'));
            }

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
            $this->phpSerializer && $this->phpSerializer->enableClassNotFoundCreation();

            $worker->run();
        } finally {
            $this->phpSerializer && $this->phpSerializer->enableClassNotFoundCreation(false);
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
            try {
                $this->phpSerializer && $this->phpSerializer->enableClassNotFoundCreation();

                $envelope = $receiver->find($id);
            } finally {
                $this->phpSerializer && $this->phpSerializer->enableClassNotFoundCreation(false);
            }

            if (null === $envelope) {
                throw new RuntimeException(sprintf('The message "%s" was not found.', $id));
            }

            $singleReceiver = new SingleMessageReceiver($receiver, $envelope);
            $this->runWorker($singleReceiver, $io, $shouldForce);
        }
    }

    private function retrySpecificEnvelop(array $envelopes, SymfonyStyle $io, bool $shouldForce)
    {
        $receiver = $this->getReceiver();

        foreach ($envelopes as $envelope) {
            $singleReceiver = new SingleMessageReceiver($receiver, $envelope);
            $this->runWorker($singleReceiver, $io, $shouldForce);
        }
    }
}
