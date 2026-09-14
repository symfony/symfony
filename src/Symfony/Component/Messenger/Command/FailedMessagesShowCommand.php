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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Failure\FailedMessageFilter;
use Symfony\Component\Messenger\Failure\FailedMessageRepository;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

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
                new InputOption('transport', null, InputOption::VALUE_REQUIRED, 'Use a specific failure transport', self::DEFAULT_TRANSPORT_OPTION),
                new InputOption('stats', null, InputOption::VALUE_NONE, 'Display the message count by class'),
                new InputOption('class-filter', null, InputOption::VALUE_REQUIRED, 'Filter by a specific class name'),
                new InputOption('failed-after', null, InputOption::VALUE_REQUIRED, 'Only select messages that failed at or after this date; messages with no known failure time are never selected'),
                new InputOption('failed-before', null, InputOption::VALUE_REQUIRED, 'Only select messages that failed at or before this date; messages with no known failure time are never selected'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> shows message that are pending in the failure transport.

                    <info>php %command.full_name%</info>

                Or look at a specific message by its id:

                    <info>php %command.full_name% {id}</info>

                The listing can be narrowed down by class name, by failure time, or by both:

                    <info>php %command.full_name% --class-filter='App\Message\SendEmail'</info>
                    <info>php %command.full_name% --failed-after='-1 hour'</info>
                    <info>php %command.full_name% --failed-after='2024-05-01 08:00' --failed-before='2024-05-01 09:30'</info>

                The "--failed-after" and "--failed-before" options accept any expression supported by
                DateTimeImmutable and both bounds are inclusive. The failure time is the one shown in the
                "Failed at" column, so messages that were never redelivered are never selected by these
                options. Filters cannot be combined with a message id and they also narrow down "--stats".
                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $id = $input->getArgument('id');
        $filter = $this->getFilter($input, null !== $id);

        $failureTransportName = $input->getOption('transport');
        if (self::DEFAULT_TRANSPORT_OPTION === $failureTransportName) {
            $this->printWarningAvailableFailureTransports($errorIo, $this->getGlobalFailureReceiverName());
        }
        if ('' === $failureTransportName || null === $failureTransportName) {
            $failureTransportName = $this->interactiveChooseFailureTransport($errorIo);
        }
        $failureTransportName = self::DEFAULT_TRANSPORT_OPTION === $failureTransportName ? $this->getGlobalFailureReceiverName() : $failureTransportName;

        $this->printPendingMessagesMessage($failureTransportName, $io);

        if (!$this->repository->supportsListing($failureTransportName)) {
            throw new RuntimeException(\sprintf('The "%s" receiver does not support listing or showing specific messages.', $failureTransportName));
        }

        if ($input->getOption('stats')) {
            $max = $input->hasParameterOption(['--max'], true) ? $input->getOption('max') : null;
            $this->listMessagesPerClass($failureTransportName, $io, $max, $filter);
        } elseif (null === $id) {
            $this->listMessages($failureTransportName, $io, $errorIo, $input->getOption('max'), $filter);
        } else {
            $this->showMessage($failureTransportName, $id, $io, $errorIo);
        }

        return 0;
    }

    private function listMessages(string $failedTransportName, SymfonyStyle $io, SymfonyStyle $errorIo, int $max, FailedMessageFilter $filter): void
    {
        $rows = [];

        if ($filter->class) {
            $errorIo->comment(\sprintf('Displaying only \'%s\' messages', $filter->class));
        }
        if ($filter->failedAfter) {
            $errorIo->comment(\sprintf('Displaying only messages that failed after %s', $filter->failedAfter->format('Y-m-d H:i:s')));
        }
        if ($filter->failedBefore) {
            $errorIo->comment(\sprintf('Displaying only messages that failed before %s', $filter->failedBefore->format('Y-m-d H:i:s')));
        }

        foreach ($this->repository->all($failedTransportName, $filter, $max) as $envelope) {
            $lastRedeliveryStamp = $envelope->last(RedeliveryStamp::class);
            $lastErrorDetailsStamp = $envelope->last(ErrorDetailsStamp::class);

            $rows[] = [
                FailedMessageRepository::getMessageId($envelope),
                $envelope->getMessage()::class,
                null === $lastRedeliveryStamp ? '' : $lastRedeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s'),
                $lastErrorDetailsStamp?->getExceptionMessage() ?? '',
            ];
        }

        $rowsCount = \count($rows);

        if (0 === $rowsCount) {
            $io->success('No failed messages were found.');

            return;
        }

        $io->table(['Id', 'Class', 'Failed at', 'Error'], $rows);

        if ($rowsCount === $max) {
            $errorIo->comment(\sprintf('Showing first %d messages.', $max));
        } elseif (!$filter->isEmpty()) {
            $errorIo->comment(\sprintf('Showing %d message(s).', $rowsCount));
        }

        $errorIo->comment(\sprintf('Run <comment>messenger:failed:show {id} --transport=%s -vv</comment> to see message details.', $failedTransportName));
    }

    private function listMessagesPerClass(string $failedTransportName, SymfonyStyle $io, ?int $max, FailedMessageFilter $filter): void
    {
        $countPerClass = [];

        foreach ($this->repository->all($failedTransportName, $filter, $max) as $envelope) {
            $c = $envelope->getMessage()::class;

            if (!isset($countPerClass[$c])) {
                $countPerClass[$c] = [$c, 0];
            }

            ++$countPerClass[$c][1];
        }

        if (!$countPerClass) {
            $io->success('No failed messages were found.');

            return;
        }

        $io->table(['Class', 'Count'], $countPerClass);
    }

    private function showMessage(string $failedTransportName, string $id, SymfonyStyle $io, SymfonyStyle $errorIo): void
    {
        if (null === $envelope = $this->repository->find($id, $failedTransportName)) {
            throw new RuntimeException(\sprintf('The message "%s" was not found.', $id));
        }

        $this->displaySingleMessage($envelope, $io, $errorIo);

        $io->writeln([
            '',
            \sprintf(' Run <comment>messenger:failed:retry %s --transport=%s</comment> to retry this message.', $id, $failedTransportName),
            \sprintf(' Run <comment>messenger:failed:remove %s --transport=%s</comment> to delete it.', $id, $failedTransportName),
        ]);
    }
}
