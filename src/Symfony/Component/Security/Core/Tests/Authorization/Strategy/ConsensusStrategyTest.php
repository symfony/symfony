<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Authorization\Strategy;

use Symfony\Component\Security\Core\Authorization\Strategy\ConsensusStrategy;
use Symfony\Component\Security\Core\Test\AccessDecisionStrategyTestCase;

class ConsensusStrategyTest extends AccessDecisionStrategyTestCase
{
    public static function provideStrategyTests(): iterable
    {
        $strategy = new ConsensusStrategy();

        yield [$strategy, self::getVoters(1, 0, 0), true];
        yield [$strategy, self::getVoters(1, 2, 0), false];
        yield [$strategy, self::getVoters(2, 1, 0), true];
        yield [$strategy, self::getVoters(0, 0, 1), false];

        yield [$strategy, self::getVoters(2, 2, 0), true];
        yield [$strategy, self::getVoters(2, 2, 1), true];

        $strategy = new ConsensusStrategy(true);

        yield [$strategy, self::getVoters(0, 0, 1), true];

        $strategy = new ConsensusStrategy(false, false);

        yield [$strategy, self::getVoters(2, 2, 0), false];
        yield [$strategy, self::getVoters(2, 2, 1), false];
    }
}
