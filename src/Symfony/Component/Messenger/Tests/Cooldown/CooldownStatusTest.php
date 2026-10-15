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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Cooldown\CooldownStatus;

class CooldownStatusTest extends TestCase
{
    public function testInactiveStatusIsNotCoolingDown()
    {
        $status = CooldownStatus::inactive();

        $this->assertFalse($status->isCoolingDown());
        $this->assertNull($status->getEnd());
    }

    public function testUntilStatusIsCoolingDownUntilTheGivenEnd()
    {
        $end = new \DateTimeImmutable('2026-10-15 10:00:00+00:00');
        $status = CooldownStatus::until($end);

        $this->assertTrue($status->isCoolingDown());
        $this->assertSame($end, $status->getEnd());
    }

    public function testIndefiniteStatusIsCoolingDownWithoutAKnownEnd()
    {
        $status = CooldownStatus::indefinite();

        $this->assertTrue($status->isCoolingDown());
        $this->assertNull($status->getEnd());
    }
}
