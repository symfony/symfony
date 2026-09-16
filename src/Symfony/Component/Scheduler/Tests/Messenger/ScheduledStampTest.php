<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;
use Symfony\Component\Scheduler\Trigger\CallbackTrigger;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use Symfony\Component\Scheduler\Trigger\SerializedTrigger;

class ScheduledStampTest extends TestCase
{
    public function testSerializationKeepsTheContextAndDescribesTheTrigger()
    {
        $context = new MessageContext('default', 'id', new PeriodicalTrigger(5), new \DateTimeImmutable('2020-01-01T00:00:00Z'), new \DateTimeImmutable('2020-01-01T00:00:05Z'));

        $context = unserialize(serialize(new ScheduledStamp($context)))->messageContext;

        $this->assertSame('default', $context->name);
        $this->assertSame('id', $context->id);
        $this->assertEquals(new \DateTimeImmutable('2020-01-01T00:00:00Z'), $context->triggeredAt);
        $this->assertEquals(new \DateTimeImmutable('2020-01-01T00:00:05Z'), $context->nextTriggerAt);
        $this->assertInstanceOf(SerializedTrigger::class, $context->trigger);
        $this->assertSame('every 5 seconds', (string) $context->trigger);
    }

    public function testItSurvivesATransportWhenTheTriggerHoldsAClosure()
    {
        $context = new MessageContext('default', 'id', new CallbackTrigger(static fn () => null, 'nightly'), new \DateTimeImmutable());
        $serializer = new PhpSerializer();

        $envelope = $serializer->decode($serializer->encode(new Envelope(new \stdClass(), [new ScheduledStamp($context)])));

        $this->assertSame('default', $envelope->last(ScheduledStamp::class)->messageContext->name);
        $this->assertSame('nightly', (string) $envelope->last(ScheduledStamp::class)->messageContext->trigger);
    }

    public function testPayloadsWrittenBeforeTheTriggerWasDescribed()
    {
        $context = new MessageContext('default', 'id', new PeriodicalTrigger(5), new \DateTimeImmutable());
        $payload = \sprintf('O:%d:"%s":1:{s:14:"messageContext";%s}', \strlen(ScheduledStamp::class), ScheduledStamp::class, serialize($context));

        $this->assertEquals($context, unserialize($payload)->messageContext);
    }
}
