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
use Symfony\Bridge\Doctrine\Messenger\MessageRecordingEntitySubscriber;
use Symfony\Bridge\Doctrine\Tests\Fixtures\MessageRecordingEntity;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

class MessageRecordingEntitySubscriberTest extends TestCase
{
    public function testPostFlush(): void
    {
        $entity = new MessageRecordingEntity();
        $message1 = new \stdClass();
        $message2 = new \stdClass();
        $message2->a = 1;
        $entity->doRecordMessage($message1);
        $entity->doRecordMessage($message2);

        $uow = $this->createMock(UnitOfWork::class);
        $uow
            ->expects(static::once())
            ->method('getIdentityMap')
            ->willReturn([[$entity]])
        ;

        $em = $this->createMock(EntityManagerInterface::class);
        $em
            ->expects(static::once())
            ->method('getUnitOfWork')
            ->willReturn($uow)
        ;

        $args = new PostFlushEventArgs($em);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus
            ->expects(static::exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [$message1, [new DispatchAfterCurrentBusStamp()]],
                [$message2, [new DispatchAfterCurrentBusStamp()]]
            )
            ->willReturn(new Envelope(new \stdClass()))
        ;

        $subscriber = new MessageRecordingEntitySubscriber($bus);

        $subscriber->postFlush($args);
    }
}
