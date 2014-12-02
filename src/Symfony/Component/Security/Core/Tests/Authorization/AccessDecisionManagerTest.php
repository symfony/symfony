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

use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AccessDecisionManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testSupportsClass()
    {
        $manager = new AccessDecisionManager(array(
            $this->getVoterSupportsClass(true),
            $this->getVoterSupportsClass(false),
        ));
        $this->assertTrue($manager->supportsClass('FooClass'));

        $manager = new AccessDecisionManager(array(
            $this->getVoterSupportsClass(false),
            $this->getVoterSupportsClass(false),
        ));
        $this->assertFalse($manager->supportsClass('FooClass'));
    }

    public function testSupportsAttribute()
    {
        $manager = new AccessDecisionManager(array(
            $this->getVoterSupportsAttribute(true),
            $this->getVoterSupportsAttribute(false),
        ));
        $this->assertTrue($manager->supportsAttribute('foo'));

        $manager = new AccessDecisionManager(array(
            $this->getVoterSupportsAttribute(false),
            $this->getVoterSupportsAttribute(false),
        ));
        $this->assertFalse($manager->supportsAttribute('foo'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetVotersEmpty()
    {
        $manager = new AccessDecisionManager(array());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetUnsupportedStrategy()
    {
        new AccessDecisionManager(array($this->getVoter(VoterInterface::ACCESS_GRANTED)), 'fooBar');
    }

    /**
     * @dataProvider getStrategyTests
     */
    public function testStrategies($strategy, $voters, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions, $expected)
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $manager = new AccessDecisionManager($voters, $strategy, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);

        $this->assertSame($expected, $manager->decide($token, array('ROLE_FOO')));
    }

    /**
     * @dataProvider getStrategiesWith2RolesTests
     */
    public function testStrategiesWith2Roles($token, $strategy, $voter, $expected)
    {
        $manager = new AccessDecisionManager(array($voter), $strategy);

        $this->assertSame($expected, $manager->decide($token, array('ROLE_FOO', 'ROLE_BAR')));
    }

    public function getStrategiesWith2RolesTests()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        return array(
            array($token, 'affirmative', $this->getVoter(VoterInterface::ACCESS_DENIED), false),
            array($token, 'affirmative', $this->getVoter(VoterInterface::ACCESS_GRANTED), true),

            array($token, 'consensus', $this->getVoter(VoterInterface::ACCESS_DENIED), false),
            array($token, 'consensus', $this->getVoter(VoterInterface::ACCESS_GRANTED), true),

            array($token, 'unanimous', $this->getVoterFor2Roles($token, VoterInterface::ACCESS_DENIED, VoterInterface::ACCESS_DENIED), false),
            array($token, 'unanimous', $this->getVoterFor2Roles($token, VoterInterface::ACCESS_DENIED, VoterInterface::ACCESS_GRANTED), false),
            array($token, 'unanimous', $this->getVoterFor2Roles($token, VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_DENIED), false),
            array($token, 'unanimous', $this->getVoterFor2Roles($token, VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_GRANTED), true),
        );
    }

    protected function getVoterFor2Roles($token, $vote1, $vote2)
    {
        $voter = $this->getMock('Symfony\Component\Security\Core\Authorization\Voter\VoterInterface');
        $voter->expects($this->exactly(2))
              ->method('vote')
              ->will($this->returnValueMap(array(
                  array($token, null, array("ROLE_FOO"), $vote1),
                  array($token, null, array("ROLE_BAR"), $vote2),
              )))
        ;

        return $voter;
    }

    public function getStrategyTests()
    {
        return array(
            // affirmative
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(1, 0, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(1, 2, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 1, 0), false, true, false),
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 0, 1), false, true, false),
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, $this->getVoters(0, 0, 1), true, true, true),

            // consensus
            array(AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(1, 0, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(1, 2, 0), false, true, false),
            array(AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 1, 0), false, true, true),

            array(AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(0, 0, 1), false, true, false),

            array(AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(0, 0, 1), true, true, true),

            array(AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 1), false, true, true),

            array(AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 0), false, false, false),
            array(AccessDecisionManager::STRATEGY_CONSENSUS, $this->getVoters(2, 2, 1), false, false, false),

            // unanimous
            array(AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 0, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 0, 1), false, true, true),
            array(AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(1, 1, 0), false, true, false),

            array(AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(0, 0, 2), false, true, false),
            array(AccessDecisionManager::STRATEGY_UNANIMOUS, $this->getVoters(0, 0, 2), true, true, true),
        );
    }

    protected function getVoters($grants, $denies, $abstains)
    {
        $voters = array();
        for ($i = 0; $i < $grants; $i++) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_GRANTED);
        }
        for ($i = 0; $i < $denies; $i++) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_DENIED);
        }
        for ($i = 0; $i < $abstains; $i++) {
            $voters[] = $this->getVoter(VoterInterface::ACCESS_ABSTAIN);
        }

        return $voters;
    }

    protected function getVoter($vote)
    {
        $voter = $this->getMock('Symfony\Component\Security\Core\Authorization\Voter\VoterInterface');
        $voter->expects($this->any())
              ->method('vote')
              ->will($this->returnValue($vote));

        return $voter;
    }

    protected function getVoterSupportsClass($ret)
    {
        $voter = $this->getMock('Symfony\Component\Security\Core\Authorization\Voter\VoterInterface');
        $voter->expects($this->any())
              ->method('supportsClass')
              ->will($this->returnValue($ret));

        return $voter;
    }

    protected function getVoterSupportsAttribute($ret)
    {
        $voter = $this->getMock('Symfony\Component\Security\Core\Authorization\Voter\VoterInterface');
        $voter->expects($this->any())
              ->method('supportsAttribute')
              ->will($this->returnValue($ret));

        return $voter;
    }
}
