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
                new InputOption('transport', null, InputOption::VALUE_OPTIONAL, 'Use a specific failure transport', self::DEFAULT_TRANSPORT_OPTION),
                new InputOption('show-messages', null, InputOption::VALUE_NONE, 'Display messages before removing it (if multiple ids are given)'),
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
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        $failureTransportName = $input->getOption('transport');
        if (self::DEFAULT_TRANSPORT_OPTION === $failureTransportName) {
            $failureTransportName = $this->getGlobalFailureReceiverName();
        }

        $receiver = $this->getReceiver($failureTransportName);

        $shouldForce = $input->getOption('force');
        $ids = (array) $input->getArgument('id');
        $shouldDeleteAllMessages = $input->getOption('all');

        $idsCount = \count($ids);
        if (!$shouldDeleteAllMessages && !$idsCount) {
            throw new RuntimeException('Please specify at least one message id. If you want to remove all failed messages, use the "--all" option.');
        } elseif ($shouldDeleteAllMessages && $idsCount) {
            throw new RuntimeException('You cannot specify message ids when using the "--all" option.');
        }

        $shouldDisplayMessages = $input->getOption('show-messages') || 1 === $idsCount;

        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(sprintf('The "%s" receiver does not support removing specific messages.', $failureTransportName));
        }

        if ($shouldDeleteAllMessages) {
            $this->removeAllMessages($receiver, $io, $shouldForce, $shouldDisplayMessages);
        } else {
            $this->removeMessagesById($ids, $receiver, $io, $shouldForce, $shouldDisplayMessages);
        }

        return 0;
    }

    private function removeMessagesById(array $ids, ListableReceiverInterface $receiver, SymfonyStyle $io, bool $shouldForce, bool $shouldDisplayMessages): void
    {
        foreach ($ids as $id) {
            $this->phpSerializer?->acceptPhpIncompleteClass();
            try {
                $envelope = $receiver->find($id);
            } finally {
                $this->phpSerializer?->rejectPhpIncompleteClass();
            }

            if (null === $envelope) {
                $io->error(sprintf('The message with id "%s" was not found.', $id));
                continue;
            }

            if ($shouldDisplayMessages) {
                $this->displaySingleMessage($envelope, $io);
            }

            if ($shouldForce || $io->confirm('Do you want to permanently remove this message?', false)) {
                $receiver->reject($envelope);

                $io->success(sprintf('Message with id %s removed.', $id));
            } else {
                $io->note(sprintf('Message with id %s not removed.', $id));
            }
        }
    }

    private function removeAllMessages(ListableReceiverInterface $receiver, SymfonyStyle $io, bool $shouldForce, bool $shouldDisplayMessages): void
    {
        if (!$shouldForce) {
            if ($receiver instanceof MessageCountAwareInterface) {
                $question = sprintf('Do you want to permanently remove all (%d) messages?', $receiver->getMessageCount());
            } else {
                $question = 'Do you want to permanently remove all failed messages?';
            }

            if (!$io->confirm($question, false)) {
                return;
            }
        }

        $count = 0;
        foreach ($receiver->all() as $envelope) {
            if ($shouldDisplayMessages) {
                $this->displaySingleMessage($envelope, $io);
            }

            $receiver->reject($envelope);
            ++$count;
        }

        $io->note(sprintf('%d messages were removed.', $count));
    }
}
