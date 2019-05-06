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
use Symfony\Component\Messenger\Failure\FailedMessage;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
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

        $message = $envelope->getMessage();
        if (!$message instanceof FailedMessage) {
            $io->warning('Message does not appear to have been sent to this transport after failing');

            return;
        }

        $rows = [
            ['Class', \get_class($message->getFailedEnvelope()->getMessage())],
        ];

        if (null !== $id = $this->getMessageId($envelope)) {
            $rows[] = ['Message Id', $id];
        }

        $rows = array_merge($rows, [
            ['Failed at', $message->getFailedAt()->format('Y-m-d H:i:s')],
            ['Error', $message->getExceptionMessage()],
            ['Error Class', $message->getFlattenException() ? $message->getFlattenException()->getClass() : '(unknown)'],
            ['Transport', $this->getOriginalTransportName($message->getFailedEnvelope())],
        ]);

        $io->table([], $rows);

        if ($io->isVeryVerbose()) {
            $io->title('Message:');
            $dump = new Dumper($io);
            $io->writeln($dump($envelope->getMessage()));
            $io->title('Exception:');
            $io->writeln($message->getFlattenException()->getTraceAsString());
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

    private function getOriginalTransportName(Envelope $envelope): ?string
    {
        /** @var ReceivedStamp $receivedStamp */
        $receivedStamp = $envelope->last(ReceivedStamp::class);

        return null === $receivedStamp ? null : $receivedStamp->getTransportName();
    }
}
