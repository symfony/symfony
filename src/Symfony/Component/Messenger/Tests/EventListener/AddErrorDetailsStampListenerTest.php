<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;

final class AddErrorDetailsStampListenerTest extends TestCase
{
    public function testExceptionDetailsAreAdded()
    {
        $listener = new AddErrorDetailsStampListener();

        $envelope = new Envelope(new \stdClass());
        $exception = new \Exception('It failed!');
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);
        $expectedStamp = ErrorDetailsStamp::create($exception);

        $listener->onMessageFailed($event);

        $this->assertEquals($expectedStamp, $event->getEnvelope()->last(ErrorDetailsStamp::class));
    }

    public function testWorkerAddsNewErrorDetailsStampOnFailure()
    {
        $listener = new AddErrorDetailsStampListener();

        $envelope = new Envelope(new \stdClass(), [
            ErrorDetailsStamp::create(new \InvalidArgumentException('First error!')),
        ]);

        $exception = new \Exception('Second error!');
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', $exception);
        $expectedStamp = ErrorDetailsStamp::create($exception);

        $listener->onMessageFailed($event);

        $this->assertEquals($expectedStamp, $event->getEnvelope()->last(ErrorDetailsStamp::class));
        $this->assertCount(2, $event->getEnvelope()->all(ErrorDetailsStamp::class));
    }

    public function testWorkerDoesNotAddDuplicateErrorDetailsStampOnFailure()
    {
        $listener = new AddErrorDetailsStampListener();

        $envelope = new Envelope(new \stdClass(), [new \Exception('It failed!')]);
        $event = new WorkerMessageFailedEvent($envelope, 'my_receiver', new \Exception('It failed!'));

        $listener->onMessageFailed($event);

        $this->assertCount(1, $event->getEnvelope()->all(ErrorDetailsStamp::class));
    }
}
