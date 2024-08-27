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
        ], self::getAccessDecision(true, [
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_GRANTED,
        ])];

        yield [$strategy, [
            self::getVoter(VoterInterface::ACCESS_ABSTAIN),
            self::getVoter(VoterInterface::ACCESS_DENIED),
            self::getVoter(VoterInterface::ACCESS_GRANTED),
            self::getVoter(VoterInterface::ACCESS_GRANTED),
        ], self::getAccessDecision(false, [
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_DENIED,
        ])];

        yield [$strategy, self::getVoters(0, 0, 2), self::getAccessDecision(false, [
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN
        ])];

        $strategy = new PriorityStrategy(true);

        yield [$strategy, self::getVoters(0, 0, 2), self::getAccessDecision(true, [
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
        ])];
    }
}
