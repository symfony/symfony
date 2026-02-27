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
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[AsCommand(name: 'messenger:failed:remove', description: 'Remove given messages from the failure transport')]
class FailedMessagesRemoveCommand extends AbstractFailedMessagesCommand
{
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('id', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Specific message id(s) to remove'),
                new InputOption('all', null, InputOption::VALUE_NONE, 'Remove all failed messages from the transport'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Force the operation without confirmation'),
                new InputOption('transport', null, InputOption::VALUE_REQUIRED, 'Use a specific failure transport', self::DEFAULT_TRANSPORT_OPTION),
                new InputOption('show-messages', null, InputOption::VALUE_NONE, 'Display messages before removing it (if multiple ids are given)'),
                new InputOption('class-filter', null, InputOption::VALUE_REQUIRED, 'Filter by a specific class name'),
            ])
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> removes given messages that are pending in the failure transport.

                    <info>php %command.full_name% {id1} [{id2} ...]</info>

                The specific ids can be found via the messenger:failed:show command.

                You can remove all failed messages from the failure transport by using the "--all" option:

                    <info>php %command.full_name% --all</info>
                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $failureTransportName = $input->getOption('transport');
        if (self::DEFAULT_TRANSPORT_OPTION === $failureTransportName) {
            $failureTransportName = $this->getGlobalFailureReceiverName();
        }

        $receiver = $this->getReceiver($failureTransportName);

        $shouldForce = $input->getOption('force');
        $ids = (array) $input->getArgument('id');
        $shouldDeleteAllMessages = $input->getOption('all');

        $idsCount = \count($ids);

        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(\sprintf('The "%s" receiver does not support removing specific messages.', $failureTransportName));
        }

        if (!$idsCount && null !== $input->getOption('class-filter')) {
            $ids = $this->getMessageIdsByClassFilter($input->getOption('class-filter'), $receiver);
            $idsCount = \count($ids);

            if (!$idsCount) {
                throw new RuntimeException('No failed messages were found with this filter.');
            }

            if (!$io->confirm(\sprintf('Can you confirm you want to remove %d message%s?', $idsCount, 1 === $idsCount ? '' : 's'))) {
                return 0;
            }
        }

        if (!$shouldDeleteAllMessages && !$idsCount) {
            throw new RuntimeException('Please specify at least one message id. If you want to remove all failed messages, use the "--all" option.');
        } elseif ($shouldDeleteAllMessages && $idsCount) {
            throw new RuntimeException('You cannot specify message ids when using the "--all" option.');
        }

        $shouldDisplayMessages = $input->getOption('show-messages') || 1 === $idsCount;

        if ($shouldDeleteAllMessages) {
            $this->removeAllMessages($receiver, $io, $errorIo, $shouldForce, $shouldDisplayMessages);
        } else {
            $this->removeMessagesById($ids, $receiver, $io, $errorIo, $shouldForce, $shouldDisplayMessages);
        }

        return 0;
    }

    private function removeMessagesById(array $ids, ListableReceiverInterface $receiver, SymfonyStyle $io, SymfonyStyle $errorIo, bool $shouldForce, bool $shouldDisplayMessages): void
    {
        foreach ($ids as $id) {
            $this->phpSerializer?->acceptPhpIncompleteClass();
            try {
                $envelope = $receiver->find($id);
            } finally {
                $this->phpSerializer?->rejectPhpIncompleteClass();
            }

            if (null === $envelope) {
                $errorIo->error(\sprintf('The message with id "%s" was not found.', $id));
                continue;
            }

            if ($shouldDisplayMessages) {
                $this->displaySingleMessage($envelope, $io, $errorIo);
            }

            if ($shouldForce || $errorIo->confirm('Do you want to permanently remove this message?', false)) {
                $receiver->reject($envelope);

                $io->success(\sprintf('Message with id %s removed.', $id));
            } else {
                $errorIo->note(\sprintf('Message with id %s not removed.', $id));
            }
        }
    }

    private function getMessageIdsByClassFilter(string $classFilter, ListableReceiverInterface $receiver): array
    {
        $ids = [];

        $this->phpSerializer?->acceptPhpIncompleteClass();
        try {
            foreach ($receiver->all() as $envelope) {
                if ($classFilter !== $envelope->getMessage()::class) {
                    continue;
                }

                $ids[] = $this->getMessageId($envelope);
            }
        } finally {
            $this->phpSerializer?->rejectPhpIncompleteClass();
        }

        return $ids;
    }

    private function removeAllMessages(ListableReceiverInterface $receiver, SymfonyStyle $io, SymfonyStyle $errorIo, bool $shouldForce, bool $shouldDisplayMessages): void
    {
        if (!$shouldForce) {
            if ($receiver instanceof MessageCountAwareInterface) {
                $question = \sprintf('Do you want to permanently remove all (%d) messages?', $receiver->getMessageCount());
            } else {
                $question = 'Do you want to permanently remove all failed messages?';
            }

            if (!$errorIo->confirm($question, false)) {
                return;
            }
        }

        $count = 0;
        foreach ($receiver->all() as $envelope) {
            if ($shouldDisplayMessages) {
                $this->displaySingleMessage($envelope, $io, $errorIo);
            }

            $receiver->reject($envelope);
            ++$count;
        }

        $errorIo->note(\sprintf('%d messages were removed.', $count));
    }
}
