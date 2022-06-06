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
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
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

    protected $failureTransports;

    private $globalFailureReceiverName;

    /**
     * @param ServiceProviderInterface $failureTransports
     */
    public function __construct(?string $globalFailureReceiverName, $failureTransports)
    {
        $this->failureTransports = $failureTransports;
        if (!$failureTransports instanceof ServiceProviderInterface) {
            trigger_deprecation('symfony/messenger', '5.3', 'Passing a receiver as 2nd argument to "%s()" is deprecated, pass a service locator instead.', __METHOD__);

            if (null === $globalFailureReceiverName) {
                throw new InvalidArgumentException(sprintf('The argument "globalFailureReceiver" from method "%s()" must be not null if 2nd argument is not a ServiceLocator.', __METHOD__));
            }

            $this->failureTransports = new ServiceLocator([$globalFailureReceiverName => static function () use ($failureTransports) { return $failureTransports; }]);
        }
        $this->globalFailureReceiverName = $globalFailureReceiverName;

        parent::__construct();
    }

    protected function getReceiverName(): string
    {
        trigger_deprecation('symfony/messenger', '5.3', 'The method "%s()" is deprecated, use getGlobalFailureReceiverName() instead.', __METHOD__);

        return $this->globalFailureReceiverName;
    }

    protected function getGlobalFailureReceiverName(): ?string
    {
        return $this->globalFailureReceiverName;
    }

    /**
     * @return mixed
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

    /**
     * @param string|null $name
     */
    protected function getReceiver(/* string $name = null */): ReceiverInterface
    {
        if (1 > \func_num_args() && __CLASS__ !== static::class && __CLASS__ !== (new \ReflectionMethod($this, __FUNCTION__))->getDeclaringClass()->getName() && !$this instanceof \PHPUnit\Framework\MockObject\MockObject && !$this instanceof \Prophecy\Prophecy\ProphecySubjectInterface && !$this instanceof \Mockery\MockInterface) {
            trigger_deprecation('symfony/messenger', '5.3', 'The "%s()" method will have a new "string $name" argument in version 6.0, not defining it is deprecated.', __METHOD__);
        }
        $name = \func_num_args() > 0 ? func_get_arg(0) : null;

        if (null === $name = $name ?? $this->globalFailureReceiverName) {
            throw new InvalidArgumentException(sprintf('No default failure transport is defined. Available transports are: "%s".', implode('", "', array_keys($this->failureTransports->getProvidedServices()))));
        }

        if (!$this->failureTransports->has($name)) {
            throw new InvalidArgumentException(sprintf('The "%s" failure transport was not found. Available transports are: "%s".', $name, implode('", "', array_keys($this->failureTransports->getProvidedServices()))));
        }

        return $this->failureTransports->get($name);
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

    protected function interactiveChooseFailureTransport(SymfonyStyle $io)
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
