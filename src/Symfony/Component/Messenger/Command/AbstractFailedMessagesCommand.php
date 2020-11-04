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
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\TraceStub;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @internal
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

    protected function getReceiverName(): string
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

        /** @var SentToFailureTransportStamp|null $sentToFailureTransportStamp */
        $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
        /** @var RedeliveryStamp|null $lastRedeliveryStamp */
        $lastRedeliveryStamp = $envelope->last(RedeliveryStamp::class);
        /** @var ErrorDetailsStamp|null $lastErrorDetailsStamp */
        $lastErrorDetailsStamp = $envelope->last(ErrorDetailsStamp::class);
        $lastRedeliveryStampWithException = $this->getLastRedeliveryStampWithException($envelope, true);

        $rows = [
            ['Class', \get_class($envelope->getMessage())],
        ];

        if (null !== $id = $this->getMessageId($envelope)) {
            $rows[] = ['Message Id', $id];
        }

        if (null === $sentToFailureTransportStamp) {
            $io->warning('Message does not appear to have been sent to this transport after failing');
        } else {
            $failedAt = '';
            $errorMessage = '';
            $errorCode = '';
            $errorClass = '(unknown)';

            if (null !== $lastRedeliveryStamp) {
                $failedAt = $lastRedeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s');
            }

            if (null !== $lastErrorDetailsStamp) {
                $errorMessage = $lastErrorDetailsStamp->getExceptionMessage();
                $errorCode = $lastErrorDetailsStamp->getExceptionCode();
                $errorClass = $lastErrorDetailsStamp->getExceptionClass();
            } elseif (null !== $lastRedeliveryStampWithException) {
                // Try reading the errorMessage for messages that are still in the queue without the new ErrorDetailStamps.
                $errorMessage = $lastRedeliveryStampWithException->getExceptionMessage();
                if (null !== $lastRedeliveryStampWithException->getFlattenException()) {
                    $errorClass = $lastRedeliveryStampWithException->getFlattenException()->getClass();
                }
            }

            $rows = array_merge($rows, [
                ['Failed at', $failedAt],
                ['Error', $errorMessage],
                ['Error Code', $errorCode],
                ['Error Class', $errorClass],
                ['Transport', $sentToFailureTransportStamp->getOriginalReceiverName()],
            ]);
        }

        $io->table([], $rows);

        /** @var RedeliveryStamp[] $redeliveryStamps */
        $redeliveryStamps = $envelope->all(RedeliveryStamp::class);
        $io->writeln(' Message history:');
        foreach ($redeliveryStamps as $redeliveryStamp) {
            $io->writeln(sprintf('  * Message failed at <info>%s</info> and was redelivered', $redeliveryStamp->getRedeliveredAt()->format('Y-m-d H:i:s')));
        }
        $io->newLine();

        if ($io->isVeryVerbose()) {
            $io->title('Message:');
            $dump = new Dumper($io, null, $this->createCloner());
            $io->writeln($dump($envelope->getMessage()));
            $io->title('Exception:');
            $flattenException = null;
            if (null !== $lastErrorDetailsStamp) {
                $flattenException = $lastErrorDetailsStamp->getFlattenException();
            } elseif (null !== $lastRedeliveryStampWithException) {
                $flattenException = $lastRedeliveryStampWithException->getFlattenException();
            }
            $io->writeln(null === $flattenException ? '(no data)' : $dump($flattenException));
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

    protected function getLastRedeliveryStampWithException(Envelope $envelope): ?RedeliveryStamp
    {
        if (null === \func_get_args()[1]) {
            trigger_deprecation('symfony/messenger', '5.2', sprintf('Using the "getLastRedeliveryStampWithException" method in the "%s" class is deprecated, use the "Envelope::last(%s)" instead.', self::class, ErrorDetailsStamp::class));
        }

        // Use ErrorDetailsStamp instead if it is available
        if (null !== $envelope->last(ErrorDetailsStamp::class)) {
            return null;
        }

        /** @var RedeliveryStamp $stamp */
        foreach (array_reverse($envelope->all(RedeliveryStamp::class)) as $stamp) {
            if (null !== $stamp->getExceptionMessage()) {
                return $stamp;
            }
        }

        return null;
    }

    private function createCloner(): ?ClonerInterface
    {
        if (!class_exists(VarCloner::class)) {
            return null;
        }

        $cloner = new VarCloner();
        $cloner->addCasters([FlattenException::class => function (FlattenException $flattenException, array $a, Stub $stub): array {
            $stub->class = $flattenException->getClass();

            return [
                Caster::PREFIX_VIRTUAL.'message' => $flattenException->getMessage(),
                Caster::PREFIX_VIRTUAL.'code' => $flattenException->getCode(),
                Caster::PREFIX_VIRTUAL.'file' => $flattenException->getFile(),
                Caster::PREFIX_VIRTUAL.'line' => $flattenException->getLine(),
                Caster::PREFIX_VIRTUAL.'trace' => new TraceStub($flattenException->getTrace()),
            ];
        }]);

        return $cloner;
    }
}
