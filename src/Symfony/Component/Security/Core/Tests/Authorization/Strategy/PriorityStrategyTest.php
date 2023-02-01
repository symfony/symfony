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
    public function provideStrategyTests(): iterable
    {
        $strategy = new PriorityStrategy();

        yield [$strategy, [
            static::getVoter(VoterInterface::ACCESS_ABSTAIN),
            static::getVoter(VoterInterface::ACCESS_GRANTED),
            static::getVoter(VoterInterface::ACCESS_DENIED),
            static::getVoter(VoterInterface::ACCESS_DENIED),
        ], true];

        yield [$strategy, [
            static::getVoter(VoterInterface::ACCESS_ABSTAIN),
            static::getVoter(VoterInterface::ACCESS_DENIED),
            static::getVoter(VoterInterface::ACCESS_GRANTED),
            static::getVoter(VoterInterface::ACCESS_GRANTED),
        ], false];

        yield [$strategy, static::getVoters(0, 0, 2), false];

        $strategy = new PriorityStrategy(true);

        yield [$strategy, static::getVoters(0, 0, 2), true];
    }
}
