<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Messenger\DispatchEntityMessagesDoctrineSubscriber;
use Symfony\Bridge\Doctrine\Messenger\EntityMessagePreDispatchEvent;
use Symfony\Bridge\Doctrine\Tests\Fixtures\MessageRecordingEntity;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class DispatchEntityMessagesDoctrineSubscriberTest extends TestCase
{
    public function testMessagesAreDispatched(): void
    {
        $entity = new MessageRecordingEntity();
        $message1 = new \stdClass();
        $message2 = new \stdClass();
        $message2->a = 1;
        $entity->doRecordMessage($message1);
        $entity->doRecordMessage($message2);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects(static::exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [Envelope::wrap($message1, [new DispatchAfterCurrentBusStamp()])],
                [Envelope::wrap($message2, [new DispatchAfterCurrentBusStamp()])],
            )
            ->willReturn(new Envelope(new \stdClass()))
        ;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $args = $this->createPostFlushArgs([$entity]);

        $subscriber = new DispatchEntityMessagesDoctrineSubscriber($bus, $dispatcher);
        $subscriber->postFlush($args);
    }

    public function testEventIsDispatched(): void
    {
        $entity = new MessageRecordingEntity();
        $message = new \stdClass();
        $entity->doRecordMessage($message);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects(static::once())
            ->method('dispatch')
            ->with(new EntityMessagePreDispatchEvent($entity, Envelope::wrap($message, [
                new DispatchAfterCurrentBusStamp(),
            ])))
        ;

        $args = $this->createPostFlushArgs([$entity]);

        $subscriber = new DispatchEntityMessagesDoctrineSubscriber($bus, $dispatcher);
        $subscriber->postFlush($args);
    }

    private function createPostFlushArgs(array $entities): PostFlushEventArgs
    {
        $uow = $this->createMock(UnitOfWork::class);
        $uow
            ->expects(static::once())
            ->method('getIdentityMap')
            ->willReturn([$entities])
        ;

        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects(static::once())
            ->method('getUnitOfWork')
            ->willReturn($uow)
        ;

        return new PostFlushEventArgs($em);
    }
}
