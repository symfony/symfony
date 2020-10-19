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

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class FailedMessagesShowCommand extends AbstractFailedMessagesCommand
{
    protected static $defaultName = 'messenger:failed:show';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('id', InputArgument::OPTIONAL, 'Specific message id to show'),
                new InputOption('max', null, InputOption::VALUE_REQUIRED, 'Maximum number of messages to list', 50),
            ])
            ->setDescription('Shows one or more messages from the failure transport.')
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        $receiver = $this->getReceiver();
        $this->printPendingMessagesMessage($receiver, $io);

        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(sprintf('The "%s" receiver does not support listing or showing specific messages.', $this->getReceiverName()));
        }

        if (null === $id = $input->getArgument('id')) {
            $this->listMessages($io, $input->getOption('max'));
        } else {
            $this->showMessage($id, $io);
        }

        return 0;
    }

    private function listMessages(SymfonyStyle $io, int $max)
    {
        /** @var ListableReceiverInterface $receiver */
        $receiver = $this->getReceiver();
        $envelopes = $receiver->all($max);

        $rows = [];
        foreach ($envelopes as $envelope) {
            $lastRedeliveryStampWithException = $this->getLastRedeliveryStampWithException($envelope);

            $rows[] = [
                $this->getMessageId($envelope),
                \get_class($envelope->getMessage()),
                null === $lastRedeliveryStampWithException ? '' : $lastRedeliveryStampWithException->getRedeliveredAt()->format('Y-m-d H:i:s'),
                null === $lastRedeliveryStampWithException ? '' : $lastRedeliveryStampWithException->getExceptionMessage(),
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

        $io->comment('Run <comment>messenger:failed:show {id} -vv</comment> to see message details.');
    }

    private function showMessage(string $id, SymfonyStyle $io)
    {
        /** @var ListableReceiverInterface $receiver */
        $receiver = $this->getReceiver();
        $envelope = $receiver->find($id);
        if (null === $envelope) {
            throw new RuntimeException(sprintf('The message "%s" was not found.', $id));
        }

        $this->displaySingleMessage($envelope, $io);

        $io->writeln([
            '',
            sprintf(' Run <comment>messenger:failed:retry %s</comment> to retry this message.', $id),
            sprintf(' Run <comment>messenger:failed:remove %s</comment> to delete it.', $id),
        ]);
    }
}
