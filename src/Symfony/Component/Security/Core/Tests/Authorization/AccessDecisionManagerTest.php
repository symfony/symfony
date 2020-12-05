<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AccessDecisionManagerTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testSetUnsupportedStrategy()
    {
        $this->expectException('InvalidArgumentException');
        new AccessDecisionManager([$this->getVoter(VoterInterface::ACCESS_GRANTED)], 'fooBar');
    }

    /**
     * @dataProvider getStrategyTests
     */
    public function testStrategies($strategy, $voters, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions, $expected)
    {
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $manager = new AccessDecisionManager($voters, $strategy, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);

        $this->assertSame($expected, $manager->decide($token, ['ROLE_FOO']));
    }

    /**
     * @dataProvider provideStrategies
     * @group legacy
     */
    public function testDeprecatedVoter($strategy)
    {
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $manager = new AccessDecisionManager([$this->getVoter(3)], $strategy);

        $this->expectDeprecation('Since symfony/security-core 5.3: Returning "3" in "%s::vote()" is deprecated, return one of "Symfony\Component\Security\Core\Authorization\Voter\VoterInterface" constants: "ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN".');

        $manager->decide($token, ['ROLE_FOO']);
    }

    public function getStrategyTests()
    {
        return [
            // affirmative
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(1, 2, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 1, 0), false, true, false],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 0, 1), false, true, false],
            [AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 0, 1), true, true, true],

            // consensus
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(1, 2, 0), false, true, false],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 1, 0), false, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(0, 0, 1), false, true, false],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(0, 0, 1), true, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 1), false, true, true],

            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 0), false, false, false],
            [AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 1), false, false, false],

            // unanimous
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 0, 0), false, true, true],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 0, 1), false, true, true],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 1, 0), false, true, false],

            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(0, 0, 2), false, true, false],
            [AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(0, 0, 2), true, true, true],

            // priority
            [AccessDecisionManager::STRATEGY_PRIORITY, [
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
                $this->getVoter(VoterInterface::ACCESS_GRANTED),
                $this->getVoter(VoterInterface::ACCESS_DENIED),
                $this->getVoter(VoterInterface::ACCESS_DENIED),
            ], true, true, true],

            [AccessDecisionManager::STRATEGY_PRIORITY, [
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
                $this->getVoter(VoterInterface::ACCESS_DENIED),
                $this->getVoter(VoterInterface::ACCESS_GRANTED),
                $this->getVoter(VoterInterface::ACCESS_GRANTED),
            ], true, true, false],

            [AccessDecisionManager::STRATEGY_PRIORITY, [
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
            ], false, true, false],

            [AccessDecisionManager::STRATEGY_PRIORITY, [
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
                $this->getVoter(VoterInterface::ACCESS_ABSTAIN),
            ], true, true, true],
        ];
    }

    public function provideStrategies()
    {
        yield [AccessDecisionManager::STRATEGY_AFFIRMATIVE];
        yield [AccessDecisionManager::STRATEGY_CONSENSUS];
        yield [AccessDecisionManager::STRATEGY_UNANIMOUS];
        yield [AccessDecisionManager::STRATEGY_PRIORITY];
    }

    protected function getVoters($grants, $denies, $abstains)
    {
        $voters = [];
        for ($i = 0; $i < $grants; ++$i) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_GRANTED);
        }
        for ($i = 0; $i < $denies; ++$i) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_DENIED);
        }
        for ($i = 0; $i < $abstains; ++$i) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_ABSTAIN);
        }

        return $voters;
    }

    protected function getVoter($vote)
    {
        $voter = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\Voter\VoterInterface')->getMock();
        $voter->expects($this->any())
              ->method('vote')
              ->willReturn($vote);

        return $voter;
    }
}
