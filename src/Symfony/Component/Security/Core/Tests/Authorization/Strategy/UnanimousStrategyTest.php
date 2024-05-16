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
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Test\AccessDecisionStrategyTestCase;

class UnanimousStrategyTest extends AccessDecisionStrategyTestCase
{
    public static function provideStrategyTests(): iterable
    {
        $strategy = new UnanimousStrategy();

        yield [$strategy, self::getVoters(1, 0, 0), AccessDecision::createGranted([
            Vote::createGranted(),
        ])];
        yield [$strategy, self::getVoters(1, 0, 1), AccessDecision::createGranted([
            Vote::createGranted(),
            Vote::createAbstain(),
        ])];
        yield [$strategy, self::getVoters(1, 1, 0), AccessDecision::createDenied([
            Vote::createGranted(),
            Vote::createDenied(),
        ])];

        yield [$strategy, self::getVoters(0, 0, 2), AccessDecision::createDenied([
            Vote::createAbstain(),
            Vote::createAbstain(),
        ])];

        $strategy = new UnanimousStrategy(true);

        yield [$strategy, self::getVoters(0, 0, 2), AccessDecision::createGranted([
            Vote::createAbstain(),
            Vote::createAbstain(),
        ])];
    }
}
