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
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('id', InputArgument::OPTIONAL, 'Specific message id to show'),
                new InputOption('max', null, InputOption::VALUE_REQUIRED, 'Maximum number of messages to list', 50),
                new InputOption('transport', null, InputOption::VALUE_OPTIONAL, 'Use a specific failure transport', self::DEFAULT_TRANSPORT_OPTION),
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

    /**
     * {@inheritdoc}
     */
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

        if (null === $id = $input->getArgument('id')) {
            $this->listMessages($failureTransportName, $io, $input->getOption('max'));
        } else {
            $this->showMessage($failureTransportName, $id, $io);
        }

        return 0;
    }

    private function listMessages(?string $failedTransportName, SymfonyStyle $io, int $max)
    {
        /** @var ListableReceiverInterface $receiver */
        $receiver = $this->getReceiver($failedTransportName);
        $envelopes = $receiver->all($max);

        $rows = [];
        foreach ($envelopes as $envelope) {
            /** @var RedeliveryStamp|null $lastRedeliveryStamp */
            $lastRedeliveryStamp = $envelope->last(RedeliveryStamp::class);
            /** @var ErrorDetailsStamp|null $lastErrorDetailsStamp */
            $lastErrorDetailsStamp = $envelope->last(ErrorDetailsStamp::class);

            $errorMessage = '';
            if (null !== $lastErrorDetailsStamp) {
                $errorMessage = $lastErrorDetailsStamp->getExceptionMessage();
            }

            $rows[] = [
                $this->getMessageId($envelope),
                \get_class($envelope->getMessage()),
                null === $lastRedeliveryStamp ? '' : $lastRedeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s'),
                $errorMessage,
            ];
        }

        if (0 === \count($rows)) {
            $io->success('No failed messages were found.');

            return;
        }

        $io->table(['Id', 'Class', 'Failed at', 'Error'], $rows);

        if (\count($rows) === $max) {
            $io->comment(sprintf('Showing first %d messages.', $max));
        }

        $io->comment(sprintf('Run <comment>messenger:failed:show {id} --transport=%s -vv</comment> to see message details.', $failedTransportName));
    }

    private function showMessage(?string $failedTransportName, string $id, SymfonyStyle $io)
    {
        /** @var ListableReceiverInterface $receiver */
        $receiver = $this->getReceiver($failedTransportName);
        $envelope = $receiver->find($id);
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
