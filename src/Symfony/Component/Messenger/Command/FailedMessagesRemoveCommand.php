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
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[AsCommand(name: 'messenger:failed:remove', description: 'Remove given messages from the failure transport')]
class FailedMessagesRemoveCommand extends AbstractFailedMessagesCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('id', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Specific message id(s) to remove'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Force the operation without confirmation'),
                new InputOption('transport', null, InputOption::VALUE_OPTIONAL, 'Use a specific failure transport', self::DEFAULT_TRANSPORT_OPTION),
                new InputOption('show-messages', null, InputOption::VALUE_NONE, 'Display messages before removing it (if multiple ids are given)'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> removes given messages that are pending in the failure transport.

    <info>php %command.full_name% {id1} [{id2} ...]</info>

The specific ids can be found via the messenger:failed:show command.
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
            $failureTransportName = $this->getGlobalFailureReceiverName();
        }

        $receiver = $this->getReceiver($failureTransportName);

        $shouldForce = $input->getOption('force');
        $ids = (array) $input->getArgument('id');
        $shouldDisplayMessages = $input->getOption('show-messages') || 1 === \count($ids);
        $this->removeMessages($failureTransportName, $ids, $receiver, $io, $shouldForce, $shouldDisplayMessages);

        return 0;
    }

    private function removeMessages(string $failureTransportName, array $ids, ReceiverInterface $receiver, SymfonyStyle $io, bool $shouldForce, bool $shouldDisplayMessages): void
    {
        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(sprintf('The "%s" receiver does not support removing specific messages.', $failureTransportName));
        }

        foreach ($ids as $id) {
            $envelope = $receiver->find($id);
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
}
