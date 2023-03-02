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

use Symfony\Component\Security\Core\Authorization\Strategy\AffirmativeStrategy;
use Symfony\Component\Security\Core\Test\AccessDecisionStrategyTestCase;

class AffirmativeStrategyTest extends AccessDecisionStrategyTestCase
{
    public static function provideStrategyTests(): iterable
    {
        $strategy = new AffirmativeStrategy();

        yield [$strategy, self::getVoters(1, 0, 0), true];
        yield [$strategy, self::getVoters(1, 2, 0), true];
        yield [$strategy, self::getVoters(0, 1, 0), false];
        yield [$strategy, self::getVoters(0, 0, 1), false];

        $strategy = new AffirmativeStrategy(true);

        yield [$strategy, self::getVoters(0, 0, 1), true];
    }
}
