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
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class FailedMessagesRemoveCommand extends AbstractFailedMessagesCommand
{
    protected static $defaultName = 'messenger:failed:remove';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('id', InputArgument::REQUIRED, 'Specific message id to remove'),
                new InputOption('force', null, InputOption::VALUE_NONE, 'Force the operation without confirmation'),
            ])
            ->setDescription('Remove a message from the failure transport.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> removes a message that is pending in the failure transport.

    <info>php %command.full_name% {id}</info>

The specific id can be found via the messenger:failed:show command.
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

        $shouldForce = $input->getOption('force');
        $this->removeSingleMessage($input->getArgument('id'), $receiver, $io, $shouldForce);

        return 0;
    }

    private function removeSingleMessage(string $id, ReceiverInterface $receiver, SymfonyStyle $io, bool $shouldForce)
    {
        if (!$receiver instanceof ListableReceiverInterface) {
            throw new RuntimeException(sprintf('The "%s" receiver does not support removing specific messages.', $this->getReceiverName()));
        }

        $envelope = $receiver->find($id);
        if (null === $envelope) {
            throw new RuntimeException(sprintf('The message with id "%s" was not found.', $id));
        }
        $this->displaySingleMessage($envelope, $io);

        if ($shouldForce || $io->confirm('Do you want to permanently remove this message?', false)) {
            $receiver->reject($envelope);

            $io->success('Message removed.');
        } else {
            $io->note('Message not removed.');
        }
    }
}
