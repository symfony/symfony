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

use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Test\AccessDecisionStrategyTestCase;

class UnanimousStrategyTest extends AccessDecisionStrategyTestCase
{
    public static function provideStrategyTests(): iterable
    {
        $strategy = new UnanimousStrategy();

        yield [$strategy, self::getVoters(1, 0, 0), true];
        yield [$strategy, self::getVoters(1, 0, 1), true];
        yield [$strategy, self::getVoters(1, 1, 0), false];

        yield [$strategy, self::getVoters(0, 0, 2), false];

        $strategy = new UnanimousStrategy(true);

        yield [$strategy, self::getVoters(0, 0, 2), true];
    }
}
