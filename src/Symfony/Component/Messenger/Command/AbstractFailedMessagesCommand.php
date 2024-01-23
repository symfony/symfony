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
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Helper\Dumper;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\MessageDecodingFailedStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\TraceStub;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Cloner\Stub;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @internal
 */
abstract class AbstractFailedMessagesCommand extends Command
{
    protected const DEFAULT_TRANSPORT_OPTION = 'choose';

    protected ServiceProviderInterface $failureTransports;
    protected ?PhpSerializer $phpSerializer;

    private ?string $globalFailureReceiverName;

    public function __construct(?string $globalFailureReceiverName, ServiceProviderInterface $failureTransports, ?PhpSerializer $phpSerializer = null)
    {
        $this->failureTransports = $failureTransports;
        $this->globalFailureReceiverName = $globalFailureReceiverName;
        $this->phpSerializer = $phpSerializer;

        parent::__construct();
    }

    protected function getGlobalFailureReceiverName(): ?string
    {
        return $this->globalFailureReceiverName;
    }

    protected function getMessageId(Envelope $envelope): mixed
    {
        /** @var TransportMessageIdStamp $stamp */
        $stamp = $envelope->last(TransportMessageIdStamp::class);

        return $stamp?->getId();
    }

    protected function displaySingleMessage(Envelope $envelope, SymfonyStyle $io): void
    {
        $io->title('Failed Message Details');

        /** @var SentToFailureTransportStamp|null $sentToFailureTransportStamp */
        $sentToFailureTransportStamp = $envelope->last(SentToFailureTransportStamp::class);
        /** @var RedeliveryStamp|null $lastRedeliveryStamp */
        $lastRedeliveryStamp = $envelope->last(RedeliveryStamp::class);
        /** @var ErrorDetailsStamp|null $lastErrorDetailsStamp */
        $lastErrorDetailsStamp = $envelope->last(ErrorDetailsStamp::class);
        /** @var MessageDecodingFailedStamp|null $lastMessageDecodingFailedStamp */
        $lastMessageDecodingFailedStamp = $envelope->last(MessageDecodingFailedStamp::class);

        $rows = [
            ['Class', $envelope->getMessage()::class],
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
            if (null !== $lastMessageDecodingFailedStamp) {
                $io->error('The message could not be decoded. See below an APPROXIMATIVE representation of the class.');
            }
            $dump = new Dumper($io, null, $this->createCloner());
            $io->writeln($dump($envelope->getMessage()));
            $io->title('Exception:');
            $flattenException = $lastErrorDetailsStamp?->getFlattenException();
            $io->writeln(null === $flattenException ? '(no data)' : $dump($flattenException));
        } else {
            if (null !== $lastMessageDecodingFailedStamp) {
                $io->error('The message could not be decoded.');
            }
            $io->writeln(' Re-run command with <info>-vv</info> to see more message & error details.');
        }
    }

    protected function printPendingMessagesMessage(ReceiverInterface $receiver, SymfonyStyle $io): void
    {
        if ($receiver instanceof MessageCountAwareInterface) {
            if (1 === $receiver->getMessageCount()) {
                $io->writeln('There is <comment>1</comment> message pending in the failure transport.');
            } else {
                $io->writeln(sprintf('There are <comment>%d</comment> messages pending in the failure transport.', $receiver->getMessageCount()));
            }
        }
    }

    protected function getReceiver(?string $name = null): ReceiverInterface
    {
        if (null === $name ??= $this->globalFailureReceiverName) {
            throw new InvalidArgumentException(sprintf('No default failure transport is defined. Available transports are: "%s".', implode('", "', array_keys($this->failureTransports->getProvidedServices()))));
        }

        if (!$this->failureTransports->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" failure transport was not found. Available transports are: "%s".', $name, implode('", "', array_keys($this->failureTransports->getProvidedServices()))));
        }

        return $this->failureTransports->get($name);
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

    protected function printWarningAvailableFailureTransports(SymfonyStyle $io, ?string $failureTransportName): void
    {
        $failureTransports = array_keys($this->failureTransports->getProvidedServices());
        $failureTransportsCount = \count($failureTransports);
        if ($failureTransportsCount > 1) {
            $io->writeln([
                sprintf('> Loading messages from the <comment>global</comment> failure transport <comment>%s</comment>.', $failureTransportName),
                '> To use a different failure transport, pass <comment>--transport=</comment>.',
                sprintf('> Available failure transports are: <comment>%s</comment>', implode(', ', $failureTransports)),
                "\n",
            ]);
        }
    }

    protected function interactiveChooseFailureTransport(SymfonyStyle $io): string
    {
        $failedTransports = array_keys($this->failureTransports->getProvidedServices());
        $question = new ChoiceQuestion('Select failed transport:', $failedTransports, 0);
        $question->setMultiselect(false);

        return $io->askQuestion($question);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('transport')) {
            $suggestions->suggestValues(array_keys($this->failureTransports->getProvidedServices()));

            return;
        }

        if ($input->mustSuggestArgumentValuesFor('id')) {
            $transport = $input->getOption('transport');
            $transport = self::DEFAULT_TRANSPORT_OPTION === $transport ? $this->getGlobalFailureReceiverName() : $transport;
            $receiver = $this->getReceiver($transport);

            if (!$receiver instanceof ListableReceiverInterface) {
                return;
            }

            $ids = [];
            foreach ($receiver->all(50) as $envelope) {
                $ids[] = $this->getMessageId($envelope);
            }
            $suggestions->suggestValues($ids);

            return;
        }
    }
}
