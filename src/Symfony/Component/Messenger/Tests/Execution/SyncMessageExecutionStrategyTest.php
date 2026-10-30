<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Execution;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Execution\SyncMessageExecutionStrategy;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class SyncMessageExecutionStrategyTest extends TestCase
{
    public function testHandledEnvelopeIsReleased()
    {
        $bus = $this->createBus('never');
        $message = new DummyMessage('Hey');
        $handledMessage = \WeakReference::create($message);

        (new SyncMessageExecutionStrategy($bus, static function () {}))->execute(new Envelope($message), 'transport', static function () {});
        unset($message);

        $this->assertNull($handledMessage->get());
    }

    public function testEnvelopeAckedDuringTheDispatchIsReleased()
    {
        $bus = $this->createBus('during');
        $message = new DummyMessage('Hey');
        $handledMessage = \WeakReference::create($message);

        (new SyncMessageExecutionStrategy($bus, static function () {}))->execute(new Envelope($message), 'transport', static function () {});
        unset($message);

        $this->assertNull($handledMessage->get());
    }

    public function testEnvelopeAckedAfterTheDispatchIsReleased()
    {
        $bus = $this->createBus('after');
        $message = new DummyMessage('Hey');
        $handledMessage = \WeakReference::create($message);

        (new SyncMessageExecutionStrategy($bus, static function () {}))->execute(new Envelope($message), 'transport', static function () {});
        unset($message);
        $bus->envelope->last(AckStamp::class)->ack($bus->envelope);
        $bus->envelope = null;

        $this->assertNull($handledMessage->get());
    }

    /**
     * @param 'never'|'during'|'after' $ack When the bus acks the envelope: never, during the dispatch, or after it
     */
    private function createBus(string $ack): MessageBusInterface
    {
        return new class($ack) implements MessageBusInterface {
            public ?Envelope $envelope = null;

            public function __construct(
                private string $ack,
            ) {
            }

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $envelope = Envelope::wrap($message, $stamps);

                if ('during' === $this->ack) {
                    $envelope->last(AckStamp::class)->ack($envelope);
                } elseif ('after' === $this->ack) {
                    $this->envelope = $envelope;
                }

                return $envelope;
            }
        };
    }
}
