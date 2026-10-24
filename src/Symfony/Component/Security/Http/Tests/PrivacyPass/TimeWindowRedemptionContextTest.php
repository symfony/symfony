<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\PrivacyPass;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\PrivacyPass\TimeWindowRedemptionContext;

class TimeWindowRedemptionContextTest extends TestCase
{
    public function testItDerivesTheCurrentAndThePreviousWindow()
    {
        $clock = new MockClock('2026-10-23 12:30:00');
        $context = new TimeWindowRedemptionContext('s3cr3t', 3600, $clock);

        $contexts = $context->getAcceptedContexts(new Request());

        $this->assertCount(2, $contexts);
        $this->assertSame([32, 32], array_map(\strlen(...), $contexts));
        $this->assertNotSame($contexts[0], $contexts[1]);
    }

    public function testTheContextIsStableWithinAWindow()
    {
        $clock = new MockClock('2026-10-23 12:00:00');
        $context = new TimeWindowRedemptionContext('s3cr3t', 3600, $clock);

        $first = $context->getAcceptedContexts(new Request());
        $clock->sleep(3599);

        $this->assertSame($first, $context->getAcceptedContexts(new Request()));
    }

    public function testTheContextOfAWindowBecomesThePreviousOneInTheNext()
    {
        $clock = new MockClock('2026-10-23 12:00:00');
        $context = new TimeWindowRedemptionContext('s3cr3t', 3600, $clock);

        $first = $context->getAcceptedContexts(new Request());
        $clock->sleep(3600);
        $second = $context->getAcceptedContexts(new Request());

        $this->assertSame($first[0], $second[1]);
        $this->assertNotSame($first[0], $second[0]);
    }

    public function testAnotherSecretDerivesAnotherContext()
    {
        $clock = new MockClock('2026-10-23 12:00:00');

        $this->assertNotSame(
            (new TimeWindowRedemptionContext('s3cr3t', 3600, $clock))->getAcceptedContexts(new Request()),
            (new TimeWindowRedemptionContext('other', 3600, $clock))->getAcceptedContexts(new Request()),
        );
    }

    public function testItReadsTheSystemTimeWithoutAClock()
    {
        $context = new TimeWindowRedemptionContext('s3cr3t');

        $this->assertSame([32, 32], array_map(\strlen(...), $context->getAcceptedContexts(new Request())));
    }

    public function testAChallengeIsAcceptedUntilTheEndOfTheNextWindow()
    {
        $this->assertSame(7200, (new TimeWindowRedemptionContext('s3cr3t'))->getLifetime());
        $this->assertSame(120, (new TimeWindowRedemptionContext('s3cr3t', 60))->getLifetime());
    }

    public function testItRejectsAnEmptySecret()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The secret of the redemption context must not be empty.');

        new TimeWindowRedemptionContext('');
    }

    public function testItRejectsAWindowOfNoDuration()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The window of the redemption context must be at least 1 second, "0" given.');

        new TimeWindowRedemptionContext('s3cr3t', 0);
    }
}
