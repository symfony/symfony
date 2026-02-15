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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageSkipEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\MessageDecodingFailedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\SingleMessageReceiver;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Worker;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[AsCommand(name: 'messenger:failed:retry', description: 'Retry one or more messages from the failure transport')]
class FailedMessagesRetryCommand extends AbstractFailedMessagesCommand implements SignalableCommandInterface
{
    private const DEFAULT_KEEPALIVE_INTERVAL = 5;

    private bool $shouldStop = false;
    private bool $forceExit = false;
    private ?Worker $worker = null;

    public function __construct(
        ?string $globalReceiverName,
        ServiceProviderInterface $failureTransports,
        private MessageBusInterface $messageBus,
        private EventDispatcherInterface $eventDispatcher,
        private ?LoggerInterface $logger = null,
        ?PhpSerializer $phpSerializer = null,
        private ?array $signals = null,
    ) {
        parent::__construct($globalReceiverName, $failureTransports, $phpSerializer);
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('id', InputArgument::IS_ARRAY, 'Specific message id(s) to retry'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Force action without confirmation'),
                new InputOption('transport', null, InputOption::VALUE_REQUIRED, 'Use a specific failure transport', self::DEFAULT_TRANSPORT_OPTION),
                new InputOption('keepalive', null, InputOption::VALUE_REQUIRED, 'Whether to use the transport\'s keepalive mechanism if implemented', self::DEFAULT_KEEPALIVE_INTERVAL),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> retries message in the failure transport.

                    <info>php %command.full_name%</info>

                The command will interactively ask if each message should be retried,
                discarded or skipped.

                Some transports support retrying a specific message id, which comes
                from the <info>messenger:failed:show</info> command.

                    <info>php %command.full_name% {id}</info>

                Or pass multiple ids at once to process multiple messages:

                <info>php %command.full_name% {id1} {id2} {id3}</info>

                EOF
            )
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        if ($input->hasParameterOption('--keepalive')) {
            $this->getApplication()->setAlarmInterval((int) ($input->getOption('keepalive') ?? self::DEFAULT_KEEPALIVE_INTERVAL));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));

        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();
        $errorIo->comment('Quit this command with CONTROL-C.');
        if (!$output->isVeryVerbose()) {
            $errorIo->comment('Re-run the command with a -vv option to see logs about consumed messages.');
        }

        $failureTransportName = $input->getOption('transport');
        if (self::DEFAULT_TRANSPORT_OPTION === $failureTransportName) {
            $this->printWarningAvailableFailureTransports($errorIo, $this->getGlobalFailureReceiverName());
        }
        if ('' === $failureTransportName || null === $failureTransportName) {
            $failureTransportName = $this->interactiveChooseFailureTransport($errorIo);
        }
        $failureTransportName = self::DEFAULT_TRANSPORT_OPTION === $failureTransportName ? $this->getGlobalFailureReceiverName() : $failureTransportName;

        $receiver = $this->getReceiver($failureTransportName);
        $this->printPendingMessagesMessage($receiver, $io);

        $io->writeln(\sprintf('To retry all the messages, run <comment>messenger:consume %s</comment>', $failureTransportName));

        $shouldForce = $input->getOption('force');
        $ids = $input->getArgument('id');
        if (0 === \count($ids)) {
            if (!$input->isInteractive()) {
                throw new RuntimeException('Message id must be passed when in non-interactive mode.');
            }

            $this->runInteractive($failureTransportName, $io, $errorIo, $shouldForce);

            return 0;
        }

        $this->retrySpecificIds($failureTransportName, $ids, $io, $errorIo, $shouldForce);

        if (!$this->shouldStop) {
            $io->success('All done!');
        }

        return 0;
    }

    public function getSubscribedSignals(): array
    {
        return $this->signals ?? (\extension_loaded('pcntl') ? [\SIGTERM, \SIGINT, \SIGQUIT, \SIGALRM] : []);
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        if (!$this->worker) {
            return false;
        }

        if (\SIGALRM === $signal) {
            $this->logger?->info('Sending keepalive request.', ['transport_names' => $this->worker->getMetadata()->getTransportNames()]);

            $this->worker->keepalive($this->getApplication()->getAlarmInterval());

            return false;
        }

        $this->logger?->info('Received signal {signal}.', ['signal' => $signal, 'transport_names' => $this->worker->getMetadata()->getTransportNames()]);

        $this->worker->stop();
        $this->shouldStop = true;

        return $this->forceExit ? 0 : false;
    }

    private function runInteractive(string $failureTransportName, SymfonyStyle $io, SymfonyStyle $errorIo, bool $shouldForce): void
    {
        $receiver = $this->failureTransports->get($failureTransportName);
        $count = 0;
        if ($receiver instanceof ListableReceiverInterface) {
            // for listable receivers, find the messages one-by-one
            // this avoids using get(), which for some less-robust
            // transports (like Doctrine), will cause the message
            // to be temporarily "acked", even if the user aborts
            // handling the message
            while (!$this->shouldStop) {
                $envelopes = [];
                $this->phpSerializer?->acceptPhpIncompleteClass();
                try {
                    foreach ($receiver->all(1) as $envelope) {
                        ++$count;
                        $envelopes[] = $envelope;
                    }
                } finally {
                    $this->phpSerializer?->rejectPhpIncompleteClass();
                }

                // break the loop if all messages are consumed
                if (0 === \count($envelopes)) {
                    break;
                }

                $this->retrySpecificEnvelopes($envelopes, $failureTransportName, $io, $errorIo, $shouldForce);
            }
        } else {
            // get() and ask messages one-by-one
            $count = $this->runWorker($failureTransportName, $receiver, $io, $errorIo, $shouldForce);
        }

        // avoid success message if nothing was processed
        if (1 <= $count && !$this->shouldStop) {
            $io->success('All failed messages have been handled or removed!');
        }
    }

    private function runWorker(string $failureTransportName, ReceiverInterface $receiver, SymfonyStyle $io, SymfonyStyle $errorIo, bool $shouldForce): int
    {
        $count = 0;
        $listener = function (WorkerMessageReceivedEvent $messageReceivedEvent) use ($io, $errorIo, $receiver, $shouldForce, &$count) {
            ++$count;
            $envelope = $messageReceivedEvent->getEnvelope();

            $this->displaySingleMessage($envelope, $io, $errorIo);

            if ($envelope->last(MessageDecodingFailedStamp::class)) {
                throw new \RuntimeException(\sprintf('The message with id "%s" could not decoded, it can only be shown or removed.', $this->getMessageId($envelope) ?? '?'));
            }

            $this->forceExit = true;
            try {
                $choice = $shouldForce ? 'retry' : $errorIo->choice('Please select an action', ['retry', 'delete', 'skip'], 'retry');
                $shouldHandle = 'retry' === $choice;
            } finally {
                $this->forceExit = false;
            }

            if ($shouldHandle) {
                return;
            }

            if ('skip' === $choice) {
                $this->eventDispatcher->dispatch(new WorkerMessageSkipEvent($envelope, $envelope->last(SentToFailureTransportStamp::class)->getOriginalReceiverName()));
            }

            $messageReceivedEvent->shouldHandle(false);
            $receiver->reject($envelope);
        };
        $this->eventDispatcher->addListener(WorkerMessageReceivedEvent::class, $listener);

        $this->worker = new Worker(
            [$failureTransportName => $receiver],
            $this->messageBus,
            $this->eventDispatcher,
            $this->logger
        );

        try {
            $this->worker->run();
        } finally {
            $this->worker = null;
            $this->eventDispatcher->removeListener(WorkerMessageReceivedEvent::class, $listener);
        }

        return $count;
    }

    private function retrySpecificIds(string $failureTransportName, array $ids, SymfonyStyle $io, SymfonyStyle $errorIo, bool $shouldForce): void
    {
        $receiver = $this->getReceiver($failureTransportName);

        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(\sprintf('The "%s" receiver does not support retrying messages by id.', $failureTransportName));
        }

        foreach ($ids as $id) {
            $this->phpSerializer?->acceptPhpIncompleteClass();
            try {
                $envelope = $receiver->find($id);
            } finally {
                $this->phpSerializer?->rejectPhpIncompleteClass();
            }
            if (null === $envelope) {
                throw new RuntimeException(\sprintf('The message "%s" was not found.', $id));
            }

            $singleReceiver = new SingleMessageReceiver($receiver, $envelope);
            $this->runWorker($failureTransportName, $singleReceiver, $io, $errorIo, $shouldForce);

            if ($this->shouldStop) {
                break;
            }
        }
    }

    private function retrySpecificEnvelopes(array $envelopes, string $failureTransportName, SymfonyStyle $io, SymfonyStyle $errorIo, bool $shouldForce): void
    {
        $receiver = $this->getReceiver($failureTransportName);

        foreach ($envelopes as $envelope) {
            $singleReceiver = new SingleMessageReceiver($receiver, $envelope);
            $this->runWorker($failureTransportName, $singleReceiver, $io, $errorIo, $shouldForce);

            if ($this->shouldStop) {
                break;
            }
        }
    }
}
