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
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Test\AccessDecisionStrategyTestCase;

class UnanimousStrategyTest extends AccessDecisionStrategyTestCase
{
    public static function provideStrategyTests(): iterable
    {
        $strategy = new UnanimousStrategy();

        yield [$strategy, self::getVoters(1, 0, 0), self::getAccessDecision(true, [
            VoterInterface::ACCESS_GRANTED,
            VoterInterface::ACCESS_GRANTED,
        ])];
        yield [$strategy, self::getVoters(1, 0, 1), self::getAccessDecision(true, [
            VoterInterface::ACCESS_GRANTED,
            VoterInterface::ACCESS_GRANTED,
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
        ])];
        yield [$strategy, self::getVoters(1, 1, 0), self::getAccessDecision(false, [
            VoterInterface::ACCESS_GRANTED,
            VoterInterface::ACCESS_GRANTED,
            VoterInterface::ACCESS_DENIED,
        ])];

        yield [$strategy, self::getVoters(0, 0, 2), self::getAccessDecision(false, [
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
        ])];

        $strategy = new UnanimousStrategy(true);

        yield [$strategy, self::getVoters(0, 0, 2), self::getAccessDecision(true, [
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
            VoterInterface::ACCESS_ABSTAIN,
        ])];
    }
}
