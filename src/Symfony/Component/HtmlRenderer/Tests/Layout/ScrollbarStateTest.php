<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Layout\ScrollbarState;

final class ScrollbarStateTest extends TestCase
{
    public function testCycleLocksOnlyItsVaryingAxis()
    {
        $state = new ScrollbarState();
        $policies = [['x' => false, 'y' => true, 'lock-x' => false, 'lock-y' => false]];
        $state->begin(1);
        self::assertFalse($state->settle($policies, [[true, true]]));
        $state->begin(1);
        self::assertFalse($state->settle($policies, [[false, true]]));
        self::assertTrue($policies[0]['lock-x']);
        self::assertFalse($policies[0]['lock-y']);
        $state->begin(1);
        self::assertTrue($state->settle($policies, [[false, true]]));
    }

    public function testNewlyMaterializedCandidateRestartsTheCycleHistory()
    {
        $state = new ScrollbarState();
        $policies = [['x' => false, 'y' => false, 'lock-x' => false, 'lock-y' => false]];
        $state->begin(1);
        self::assertFalse($state->settle($policies, [[true, false]]));
        $policies[] = ['x' => false, 'y' => false, 'lock-x' => false, 'lock-y' => false];
        $state->begin(2);
        self::assertFalse($state->settle($policies, [[false, false], [true, false]]));
        self::assertFalse($policies[0]['lock-x']);
        self::assertFalse($policies[1]['lock-x']);
        $state->begin(2);
        self::assertTrue($state->settle($policies, [[false, false], [true, false]]));
    }

    public function testLateCandidatesCannotExtendTheInitialResourceBudget()
    {
        $state = new ScrollbarState();
        $state->begin(1);
        for ($pass = 2; $pass <= 8; ++$pass) {
            $state->begin($pass * 100);
        }
        $this->expectException(\LengthException::class);
        $state->begin(1000);
    }

    public function testLongNonRepeatingStateSequenceFailsItsResourceBudget()
    {
        // This exercises the resolver guard, not a claim of real CSS nonconvergence.
        $state = new ScrollbarState();
        $policies = array_fill(0, 3, ['x' => false, 'y' => false, 'lock-x' => false, 'lock-y' => false]);
        for ($number = 1; $number <= 13; ++$number) {
            $state->begin(3);
            $next = [];
            for ($index = 0; $index < 3; ++$index) {
                $next[] = [(bool) ($number & (1 << ($index * 2))), (bool) ($number & (1 << ($index * 2 + 1)))];
            }
            self::assertFalse($state->settle($policies, $next));
        }
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('state resolution budget');
        $state->begin(3);
    }
}
