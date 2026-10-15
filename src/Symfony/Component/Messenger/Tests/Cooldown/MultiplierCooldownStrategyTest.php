<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Cooldown;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Cooldown\MultiplierCooldownStrategy;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class MultiplierCooldownStrategyTest extends TestCase
{
    #[DataProvider('getCooldownDurationTests')]
    public function testCooldownDurationMultipliesUpToTheMaxDelay(int $failureThreshold, int $delay, float $multiplier, int $maxDelay, array $expectedDurations)
    {
        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $strategy = new MultiplierCooldownStrategy($failureThreshold, $delay, $multiplier, $maxDelay, 0, null, $clock);
        $envelope = new Envelope(new \stdClass());

        for ($i = 1; $i < $failureThreshold; ++$i) {
            $strategy->recordFailure('transport', $envelope, new \RuntimeException());
            $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());
        }

        foreach ($expectedDurations as $duration) {
            $strategy->recordFailure('transport', $envelope, new \RuntimeException());

            $clock->sleep($duration - 0.001);
            $this->assertTrue($strategy->checkStatus('transport')->isCoolingDown());

            $clock->sleep(0.001);
            $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());
        }
    }

    public static function getCooldownDurationTests(): iterable
    {
        // failureThreshold, delay, multiplier, maxDelay, expected durations in seconds
        yield [1, 1000, 1, 0, [1, 1, 1]];
        yield [1, 1000, 2, 0, [1, 2, 4, 8]];
        yield [1, 1000, 3, 0, [1, 3, 9]];
        yield [3, 1000, 2, 0, [1, 2, 4]];
        yield [1, 1000, 2, 3000, [1, 2, 3, 3]]; // max hit
    }

    public function testJitterKeepsTheCooldownWithinItsRange()
    {
        $envelope = new Envelope(new \stdClass());

        for ($i = 0; $i < 100; ++$i) {
            $clock = new MockClock('2026-10-14 10:00:00+00:00');
            $strategy = new MultiplierCooldownStrategy(1, 1000, 2, 60000, 0.5, null, $clock);
            $strategy->recordFailure('transport', $envelope, new \RuntimeException());

            $clock->sleep(0.499);
            $this->assertTrue($strategy->checkStatus('transport')->isCoolingDown());

            $clock->sleep(1.001);
            $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());
        }
    }

    #[DataProvider('getRetryDelayTests')]
    public function testCooldownLastsAtLeastTheRetryDelayOfTheFailureUpToTheMaxDelay(\Throwable $throwable, int $maxDelay, string $expectedEnd)
    {
        $strategy = new MultiplierCooldownStrategy(1, 1000, 2, $maxDelay, 0, null, new MockClock('2026-10-14 10:00:00+00:00'));

        $strategy->recordFailure('transport', new Envelope(new \stdClass()), $throwable);

        $this->assertEquals(new \DateTimeImmutable($expectedEnd), $strategy->checkStatus('transport')->getEnd());
    }

    public static function getRetryDelayTests(): iterable
    {
        yield 'longer than the delay' => [new RecoverableMessageHandlingException('Too many requests.', 0, null, 30000), 60000, '2026-10-14 10:00:30+00:00'];
        yield 'shorter than the delay' => [new RecoverableMessageHandlingException('Too many requests.', 0, null, 500), 60000, '2026-10-14 10:00:01+00:00'];
        yield 'longer than the max delay' => [new RecoverableMessageHandlingException('Too many requests.', 0, null, 120000), 60000, '2026-10-14 10:01:00+00:00'];
        yield 'without max delay' => [new RecoverableMessageHandlingException('Too many requests.', 0, null, 120000), 0, '2026-10-14 10:02:00+00:00'];
        yield 'asked by a handler' => [new HandlerFailedException(new Envelope(new \stdClass()), ['handler' => new RecoverableMessageHandlingException('Too many requests.', 0, null, 30000)]), 60000, '2026-10-14 10:00:30+00:00'];
        yield 'asked by a handler, longer than the max delay' => [new HandlerFailedException(new Envelope(new \stdClass()), ['handler' => new RecoverableMessageHandlingException('Too many requests.', 0, null, 120000)]), 60000, '2026-10-14 10:01:00+00:00'];
    }

    public function testStatusTellsWhenTheCooldownEnds()
    {
        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $strategy = new MultiplierCooldownStrategy(1, 1000, 2, 60000, 0, null, $clock);

        $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());

        $strategy->recordFailure('transport', new Envelope(new \stdClass()), new \RuntimeException());
        $this->assertEquals(new \DateTimeImmutable('2026-10-14 10:00:01+00:00'), $strategy->checkStatus('transport')->getEnd());

        $clock->sleep(1);
        $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());
    }

    public function testSuccessResetsTheFailuresInARow()
    {
        $strategy = new MultiplierCooldownStrategy(2, 1000, 2, 60000, 0, null, new MockClock('2026-10-14 10:00:00+00:00'));
        $envelope = new Envelope(new \stdClass());

        $strategy->recordFailure('transport', $envelope, new \RuntimeException());
        $strategy->recordSuccess('transport', $envelope);
        $strategy->recordFailure('transport', $envelope, new \RuntimeException());

        $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());
    }

    public function testFailuresDuringACooldownAreNotCounted()
    {
        $clock = new MockClock('2026-10-14 10:00:00+00:00');
        $strategy = new MultiplierCooldownStrategy(1, 1000, 2, 60000, 0, null, $clock);
        $envelope = new Envelope(new \stdClass());

        $strategy->recordFailure('transport', $envelope, new \RuntimeException());
        $strategy->recordFailure('transport', $envelope, new \RuntimeException());
        $strategy->recordFailure('transport', $envelope, new \RuntimeException());

        $clock->sleep(1);
        $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());

        // the next failure is the second one in a row
        $strategy->recordFailure('transport', $envelope, new \RuntimeException());

        $clock->sleep(1.999);
        $this->assertTrue($strategy->checkStatus('transport')->isCoolingDown());

        $clock->sleep(0.001);
        $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());
    }

    public function testFailuresForbiddingARetryAreNotCounted()
    {
        $strategy = new MultiplierCooldownStrategy(1, 1000, 2, 60000, 0, null, new MockClock('2026-10-14 10:00:00+00:00'));
        $envelope = new Envelope(new \stdClass());

        $strategy->recordFailure('transport', $envelope, new UnrecoverableMessageHandlingException());
        $strategy->recordFailure('transport', $envelope, new HandlerFailedException($envelope, ['handler' => new UnrecoverableMessageHandlingException()]));
        $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());

        $strategy->recordFailure('transport', $envelope, new RecoverableMessageHandlingException());
        $this->assertTrue($strategy->checkStatus('transport')->isCoolingDown());
    }

    public function testRedeliveriesRejectedBeforeHandlingAreNotCounted()
    {
        $strategy = new MultiplierCooldownStrategy(1, 1000, 2, 60000, 0, null, new MockClock('2026-10-14 10:00:00+00:00'));

        $strategy->recordFailure('transport', new Envelope(new \stdClass()), new RejectRedeliveredMessageException());

        $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());
    }

    public function testFetchFailuresAreCounted()
    {
        $strategy = new MultiplierCooldownStrategy(2, 1000, 2, 60000, 0, null, new MockClock('2026-10-14 10:00:00+00:00'));

        $strategy->recordFailure('transport', null, new TransportException('Connection refused.'));
        $this->assertFalse($strategy->checkStatus('transport')->isCoolingDown());

        $strategy->recordFailure('transport', null, new TransportException('Connection refused.'));
        $this->assertEquals(new \DateTimeImmutable('2026-10-14 10:00:01+00:00'), $strategy->checkStatus('transport')->getEnd());
    }

    public function testTransportsCoolDownSeparately()
    {
        $strategy = new MultiplierCooldownStrategy(1, 1000, 2, 60000, 0.1, null, new MockClock('2026-10-14 10:00:00+00:00'));

        $strategy->recordFailure('first', new Envelope(new \stdClass()), new \RuntimeException());

        $this->assertTrue($strategy->checkStatus('first')->isCoolingDown());
        $this->assertFalse($strategy->checkStatus('second')->isCoolingDown());
    }

    public function testCooldownStartIsLogged()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Transport {transport} cools down for {delay} ms after {count} failure(s) in a row.', ['transport' => 'transport', 'delay' => 1000, 'count' => 2]);

        $strategy = new MultiplierCooldownStrategy(2, 1000, 2, 60000, 0, $logger, new MockClock('2026-10-14 10:00:00+00:00'));
        $envelope = new Envelope(new \stdClass());

        $strategy->recordFailure('transport', $envelope, new \RuntimeException());
        $strategy->recordFailure('transport', $envelope, new \RuntimeException());
    }

    #[DataProvider('getInvalidArgumentTests')]
    public function testConstructorRejectsInvalidArguments(int $failureThreshold, int $delay, float $multiplier, int $maxDelay, float $jitter, string $expectedMessage)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new MultiplierCooldownStrategy($failureThreshold, $delay, $multiplier, $maxDelay, $jitter);
    }

    public static function getInvalidArgumentTests(): iterable
    {
        yield [0, 1000, 2, 0, 0, 'Failure threshold must be greater than zero: "0" given.'];
        yield [1, -1, 2, 0, 0, 'Delay must be greater than or equal to zero: "-1" given.'];
        yield [1, 1000, 0.5, 0, 0, 'Multiplier must be greater than or equal to one: "0.5" given.'];
        yield [1, 1000, 2, -1, 0, 'Max delay must be greater than or equal to zero: "-1" given.'];
        yield [1, 1000, 2, 0, 1.5, 'Jitter must be between 0 and 1: "1.5" given.'];
    }
}
