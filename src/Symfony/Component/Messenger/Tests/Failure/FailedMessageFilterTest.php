<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Failure;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Failure\FailedMessageFilter;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class FailedMessageFilterTest extends TestCase
{
    public function testAnEmptyFilterMatchesEverything()
    {
        $filter = new FailedMessageFilter();

        $this->assertTrue($filter->isEmpty());
        $this->assertTrue($filter->matches(new Envelope(new DummyMessage('a'))));
    }

    public function testClassFilter()
    {
        $filter = new FailedMessageFilter(DummyMessage::class);

        $this->assertFalse($filter->isEmpty());
        $this->assertTrue($filter->matches(new Envelope(new DummyMessage('a'))));
        $this->assertFalse($filter->matches(new Envelope(new \stdClass())));
    }

    public function testTimeWindowNeverSelectsAMessageWithNoKnownFailureTime()
    {
        $filter = new FailedMessageFilter(failedAfter: new \DateTimeImmutable('2024-05-01 08:00'));

        $this->assertFalse($filter->matches(new Envelope(new DummyMessage('a'))));
    }

    #[DataProvider('timeWindowProvider')]
    public function testTimeWindowBoundsAreInclusive(?string $after, ?string $before, bool $expected)
    {
        $envelope = new Envelope(new DummyMessage('a'), [
            new RedeliveryStamp(0, redeliveredAt: new \DateTimeImmutable('2024-05-01 09:00')),
        ]);

        $filter = new FailedMessageFilter(
            failedAfter: $after ? new \DateTimeImmutable($after) : null,
            failedBefore: $before ? new \DateTimeImmutable($before) : null,
        );

        $this->assertSame($expected, $filter->matches($envelope));
    }

    public static function timeWindowProvider(): iterable
    {
        yield 'after, before the failure' => ['2024-05-01 08:00', null, true];
        yield 'after, exactly at the failure' => ['2024-05-01 09:00', null, true];
        yield 'after, past the failure' => ['2024-05-01 10:00', null, false];
        yield 'before, past the failure' => [null, '2024-05-01 10:00', true];
        yield 'before, exactly at the failure' => [null, '2024-05-01 09:00', true];
        yield 'before, ahead of the failure' => [null, '2024-05-01 08:00', false];
        yield 'both, surrounding' => ['2024-05-01 08:00', '2024-05-01 10:00', true];
        yield 'both, excluding' => ['2024-05-01 10:00', '2024-05-01 11:00', false];
    }

    public function testClassAndTimeWindowAreCombined()
    {
        $envelope = new Envelope(new DummyMessage('a'), [
            new RedeliveryStamp(0, redeliveredAt: new \DateTimeImmutable('2024-05-01 09:00')),
        ]);

        $this->assertFalse((new FailedMessageFilter(\stdClass::class, new \DateTimeImmutable('2024-05-01 08:00')))->matches($envelope));
        $this->assertTrue((new FailedMessageFilter(DummyMessage::class, new \DateTimeImmutable('2024-05-01 08:00')))->matches($envelope));
    }
}
