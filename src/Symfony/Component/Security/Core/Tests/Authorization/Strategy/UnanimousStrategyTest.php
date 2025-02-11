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
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Test\AccessDecisionStrategyTestCase;

class UnanimousStrategyTest extends AccessDecisionStrategyTestCase
{
    public static function provideStrategyTests(): iterable
    {
        $strategy = new UnanimousStrategy();

        yield [$strategy, self::getVoters(1, 0, 0), true, [1]];
        yield [$strategy, self::getVoters(1, 0, 1), true, [1, 0]];
        yield [$strategy, self::getVoters(1, 1, 0), false, [1, -1]];

        yield [$strategy, self::getVoters(0, 0, 2), false, [0, 0]];

        $strategy = new UnanimousStrategy(true, []);

        yield [$strategy, self::getVoters(0, 0, 2), true, [0, 0]];

        yield [$strategy, [
            self::getVoterWithVoteObject(1),
            self::getVoter(-1),
            self::getVoter(0),
            self::getVoterWithVoteObject(1),
        ], false, [new Vote(1), -1]];

        yield [$strategy, [
            self::getVoterWithVoteObject(-1),
            self::getVoter(1),
            self::getVoter(0),
            self::getVoterWithVoteObject(1),
        ], false, [new Vote(-1)]];

        yield [$strategy, [
            self::getVoterWithVoteObject(0),
            self::getVoter(0),
            self::getVoter(0),
            self::getVoterWithVoteObject(1),
        ], true, [
            new Vote(0),
            0,
            0,
            new Vote(1)
        ]];
    }
}
