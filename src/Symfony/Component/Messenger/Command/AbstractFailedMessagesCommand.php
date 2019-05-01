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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @internal
 * @experimental in 4.3
 */
abstract class AbstractFailedMessagesCommand extends Command
{
    private $receiverName;
    private $receiver;

    public function __construct(string $receiverName, ReceiverInterface $receiver)
    {
        $this->receiverName = $receiverName;
        $this->receiver = $receiver;

        parent::__construct();
    }

    protected function getReceiverName()
    {
        return $this->receiverName;
    }

    /**
     * @return mixed|null
     */
    protected function getMessageId(Envelope $envelope)
    {
        /** @var TransportMessageIdStamp $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);

        return null !== $stamp ? $stamp->getId() : null;
    }

    protected function displaySingleMessage(Envelope $envelope, SymfonyStyle $io)
    {
        $io->title('Failed Message Details');

        /** @var SentToFailureTransportStamp $sentToFailureTransportStamp */
        $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);

        $rows = [
            ['Class', \get_class($envelope->getMessage())],
        ];

        if (null !== $id = $this->getMessageId($envelope)) {
            $rows[] = ['Message Id', $id];
        }

        if (null === $sentToFailureTransportStamp) {
            $io->warning('Message does not appear to have been sent to this transport after failing');
        } else {
            $rows = array_merge($rows, [
                ['Failed at', $sentToFailureTransportStamp->getSentAt()->format('Y-m-d H:i:s')],
                ['Error', $sentToFailureTransportStamp->getExceptionMessage()],
                ['Error Class', $sentToFailureTransportStamp->getFlattenException() ? $sentToFailureTransportStamp->getFlattenException()->getClass() : '(unknown)'],
                ['Transport', $sentToFailureTransportStamp->getOriginalReceiverName()],
            ]);
        }

        $io->table([], $rows);

        if ($io->isVeryVerbose()) {
            $io->title('Message:');
            $dump = new Dumper($io);
            $io->writeln($dump($envelope->getMessage()));
            $io->title('Exception:');
            $io->writeln($sentToFailureTransportStamp->getFlattenException()->getTraceAsString());
        } else {
            $io->writeln(' Re-run command with <info>-vv</info> to see more message & error details.');
        }
    }

    protected function printPendingMessagesMessage(ReceiverInterface $receiver, SymfonyStyle $io)
    {
        if ($receiver instanceof MessageCountAwareInterface) {
            if (1 === $receiver->getMessageCount()) {
                $io->writeln('There is <comment>1</comment> message pending in the failure transport.');
            } else {
                $io->writeln(sprintf('There are <comment>%d</comment> messages pending in the failure transport.', $receiver->getMessageCount()));
            }
        }
    }

    protected function getReceiver(): ReceiverInterface
    {
        return $this->receiver;
    }
}
