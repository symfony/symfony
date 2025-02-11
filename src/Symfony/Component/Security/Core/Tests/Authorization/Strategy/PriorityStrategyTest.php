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

use Symfony\Component\Security\Core\Authorization\Strategy\PriorityStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Test\AccessDecisionStrategyTestCase;

class PriorityStrategyTest extends AccessDecisionStrategyTestCase
{
    public static function provideStrategyTests(): iterable
    {
        $strategy = new PriorityStrategy();

        yield [$strategy, [
            self::getVoter(VoterInterface::ACCESS_ABSTAIN),
            self::getVoter(VoterInterface::ACCESS_GRANTED),
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_DENIED),
        ], true, [0, 1]];

        yield [$strategy, [
            self::getVoter(VoterInterface::ACCESS_ABSTAIN),
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_GRANTED),
            self::getVoter(VoterInterface::ACCESS_GRANTED),
        ], false, [0, -1]];

        yield [$strategy, [
            self::getVoterWithVoteObject(1),
            self::getVoter(-1),
            self::getVoter(0),
            self::getVoterWithVoteObject(1),
        ], true, [new Vote(1)]];

        yield [$strategy, [
            self::getVoterWithVoteObject(0),
            self::getVoter(-1),
            self::getVoter(0),
            self::getVoterWithVoteObject(1),
        ], false, [new Vote(0), -1]];

        yield [$strategy, [
            self::getVoterWithVoteObject(-1),
            self::getVoter(1),
            self::getVoter(0),
            self::getVoterWithVoteObject(1),
        ], false, [new Vote(-1)]];

        yield [$strategy, self::getVoters(0, 0, 2), false, [0, 0]];

        $strategy = new PriorityStrategy(true);

        yield [$strategy, self::getVoters(0, 0, 2), true, [0, 0]];
    }
}
