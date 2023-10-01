<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization\Strategy;

use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\Strategy\ConsensusStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Test\AccessDecisionStrategyTestCase;

class ConsensusStrategyTest extends AccessDecisionStrategyTestCase
{
    public static function provideStrategyTests(): iterable
    {
        $strategy = new ConsensusStrategy();

        yield [$strategy, self::getVoters(1, 0, 0), AccessDecision::createGranted([
            Vote::createGranted(),
        ])];
        yield [$strategy, self::getVoters(1, 2, 0), AccessDecision::createDenied([
            Vote::createGranted(),
            Vote::createDenied(),
            Vote::createDenied(),
        ])];
        yield [$strategy, self::getVoters(2, 1, 0), AccessDecision::createGranted([
            Vote::createGranted(),
            Vote::createGranted(),
            Vote::createDenied(),
        ])];
        yield [$strategy, self::getVoters(0, 0, 1), AccessDecision::createDenied([
            Vote::createAbstain(),
        ])];

        yield [$strategy, self::getVoters(2, 2, 0), AccessDecision::createGranted([
            Vote::createGranted(),
            Vote::createGranted(),
            Vote::createDenied(),
            Vote::createDenied(),
        ])];
        yield [$strategy, self::getVoters(2, 2, 1), AccessDecision::createGranted([
            Vote::createGranted(),
            Vote::createGranted(),
            Vote::createDenied(),
            Vote::createDenied(),
            Vote::createAbstain(),
        ])];

        $strategy = new ConsensusStrategy(true);

        yield [$strategy, self::getVoters(0, 0, 1), AccessDecision::createGranted([
            Vote::createAbstain(),
        ])];

        $strategy = new ConsensusStrategy(false, false);

        yield [$strategy, self::getVoters(2, 2, 0), AccessDecision::createDenied([
            Vote::createGranted(),
            Vote::createGranted(),
            Vote::createDenied(),
            Vote::createDenied(),
        ])];
        yield [$strategy, self::getVoters(2, 2, 1), AccessDecision::createDenied([
            Vote::createGranted(),
            Vote::createGranted(),
            Vote::createDenied(),
            Vote::createDenied(),
            Vote::createAbstain(),
        ])];
    }
}
