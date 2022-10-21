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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[AsCommand(name: 'messenger:failed:show', description: 'Show one or more messages from the failure transport')]
class FailedMessagesShowCommand extends AbstractFailedMessagesCommand
{
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('id', InputArgument::OPTIONAL, 'Specific message id to show'),
                new InputOption('max', null, InputOption::VALUE_REQUIRED, 'Maximum number of messages to list', 50),
                new InputOption('transport', null, InputOption::VALUE_OPTIONAL, 'Use a specific failure transport', self::DEFAULT_TRANSPORT_OPTION),
                new InputOption('stats', null, InputOption::VALUE_NONE, 'Display the message count by class'),
                new InputOption('class-filter', null, InputOption::VALUE_REQUIRED, 'Filter by a specific class name'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> shows message that are pending in the failure transport.

    <info>php %command.full_name%</info>

Or look at a specific message by its id:

    <info>php %command.full_name% {id}</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        $failureTransportName = $input->getOption('transport');
        if (self::DEFAULT_TRANSPORT_OPTION === $failureTransportName) {
            $this->printWarningAvailableFailureTransports($io, $this->getGlobalFailureReceiverName());
        }
        if ('' === $failureTransportName || null === $failureTransportName) {
            $failureTransportName = $this->interactiveChooseFailureTransport($io);
        }
        $failureTransportName = self::DEFAULT_TRANSPORT_OPTION === $failureTransportName ? $this->getGlobalFailureReceiverName() : $failureTransportName;

        $receiver = $this->getReceiver($failureTransportName);

        $this->printPendingMessagesMessage($receiver, $io);

        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(sprintf('The "%s" receiver does not support listing or showing specific messages.', $failureTransportName));
        }

        if ($input->getOption('stats')) {
            $this->listMessagesPerClass($failureTransportName, $io, $input->getOption('max'));
        } elseif (null === $id = $input->getArgument('id')) {
            $this->listMessages($failureTransportName, $io, $input->getOption('max'), $input->getOption('class-filter'));
        } else {
            $this->showMessage($failureTransportName, $id, $io);
        }

        return 0;
    }

    private function listMessages(?string $failedTransportName, SymfonyStyle $io, int $max, string $classFilter = null)
    {
        /** @var ListableReceiverInterface $receiver */
        $receiver = $this->getReceiver($failedTransportName);
        $envelopes = $receiver->all($max);

        $rows = [];

        if ($classFilter) {
            $io->comment(sprintf('Displaying only \'%s\' messages', $classFilter));
        }

        $this->phpSerializer?->acceptPhpIncompleteClass();
        try {
            foreach ($envelopes as $envelope) {
                $currentClassName = \get_class($envelope->getMessage());

                if ($classFilter && $classFilter !== $currentClassName) {
                    continue;
                }

                /** @var RedeliveryStamp|null $lastRedeliveryStamp */
                $lastRedeliveryStamp = $envelope->last(RedeliveryStamp::class);
                /** @var ErrorDetailsStamp|null $lastErrorDetailsStamp */
                $lastErrorDetailsStamp = $envelope->last(ErrorDetailsStamp::class);

                $rows[] = [
                    $this->getMessageId($envelope),
                    $currentClassName,
                    null === $lastRedeliveryStamp ? '' : $lastRedeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s'),
                    $lastErrorDetailsStamp?->getExceptionMessage() ?? '',
                ];
            }
        } finally {
            $this->phpSerializer?->rejectPhpIncompleteClass();
        }

        $rowsCount = \count($rows);

        if (0 === $rowsCount) {
            $io->success('No failed messages were found.');

            return;
        }

        $io->table(['Id', 'Class', 'Failed at', 'Error'], $rows);

        if ($rowsCount === $max) {
            $io->comment(sprintf('Showing first %d messages.', $max));
        } elseif ($classFilter) {
            $io->comment(sprintf('Showing %d message(s).', $rowsCount));
        }

        $io->comment(sprintf('Run <comment>messenger:failed:show {id} --transport=%s -vv</comment> to see message details.', $failedTransportName));
    }

    private function listMessagesPerClass(?string $failedTransportName, SymfonyStyle $io, int $max)
    {
        /** @var ListableReceiverInterface $receiver */
        $receiver = $this->getReceiver($failedTransportName);
        $envelopes = $receiver->all($max);

        $countPerClass = [];

        $this->phpSerializer?->acceptPhpIncompleteClass();
        try {
            foreach ($envelopes as $envelope) {
                $c = \get_class($envelope->getMessage());

                if (!isset($countPerClass[$c])) {
                    $countPerClass[$c] = [$c, 0];
                }

                ++$countPerClass[$c][1];
            }
        } finally {
            $this->phpSerializer?->rejectPhpIncompleteClass();
        }

        if (0 === \count($countPerClass)) {
            $io->success('No failed messages were found.');

            return;
        }

        $io->table(['Class', 'Count'], $countPerClass);
    }

    private function showMessage(?string $failedTransportName, string $id, SymfonyStyle $io)
    {
        /** @var ListableReceiverInterface $receiver */
        $receiver = $this->getReceiver($failedTransportName);
        $this->phpSerializer?->acceptPhpIncompleteClass();
        try {
            $envelope = $receiver->find($id);
        } finally {
            $this->phpSerializer?->rejectPhpIncompleteClass();
        }
        if (null === $envelope) {
            throw new RuntimeException(sprintf('The message "%s" was not found.', $id));
        }

        $this->displaySingleMessage($envelope, $io);

        $io->writeln([
            '',
            sprintf(' Run <comment>messenger:failed:retry %s --transport=%s</comment> to retry this message.', $id, $failedTransportName),
            sprintf(' Run <comment>messenger:failed:remove %s --transport=%s</comment> to delete it.', $id, $failedTransportName),
        ]);
    }
}
