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
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Test\AccessDecisionStrategyTestCase;

class AffirmativeStrategyTest extends AccessDecisionStrategyTestCase
{
    public static function provideStrategyTests(): iterable
    {
        $strategy = new AffirmativeStrategy();

        yield [$strategy, self::getVoters(1, 0, 0), true, [1]];
        yield [$strategy, self::getVoters(1, 2, 0), true, [1]];
        yield [$strategy, self::getVoters(0, 1, 0), false, [-1]];
        yield [$strategy, self::getVoters(0, 0, 1), false, [0]];

        yield [$strategy, [
            self::getVoterWithVoteObject(0),
            self::getVoter(-1),
            self::getVoter(0),
            self::getVoterWithVoteObject(1),
        ], true, [
            new Vote(0),
            -1,
            0,
            new Vote(1),
        ]];

        yield [$strategy, [
            self::getVoterWithVoteObject(0),
            self::getVoter(-1),
            self::getVoter(0),
            self::getVoterWithVoteObject(0),
        ], false, [
            new Vote(0),
            -1,
            0,
            new Vote(0),
        ]];

        yield [$strategy, [
            self::getVoterWithVoteObject(0),
            self::getVoter(1),
            self::getVoter(0),
            self::getVoterWithVoteObject(-1),
        ], true, [
           new Vote(0),
           1,
       ]];

        yield [$strategy, [
            self::getVoterWithVoteObject(1),
            self::getVoter(-1),
            self::getVoter(0),
            self::getVoterWithVoteObject(-1),
        ], true, [
           new Vote(1),
       ]];

        $strategy = new AffirmativeStrategy(true);

        yield [$strategy, self::getVoters(0, 0, 1), true, [0]];
    }
}
