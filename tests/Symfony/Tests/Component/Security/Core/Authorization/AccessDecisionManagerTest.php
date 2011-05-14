<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AccessDecisionManagerTest extends \PHPUnit_Framework_TestCase
{
    public function testSupportsClass()
    {
        $strategy = $this->getAccessStrategy(array(
            $this->getVoterSupportsClass(true),
            $this->getVoterSupportsClass(false),
        ));

        $manager = new AccessDecisionManager($strategy);
        $this->assertTrue($manager->supportsClass('FooClass'));

        $strategy = $this->getAccessStrategy(array(
            $this->getVoterSupportsClass(false),
            $this->getVoterSupportsClass(false),
        ));

        $manager = new AccessDecisionManager($strategy);
        $this->assertFalse($manager->supportsClass('FooClass'));
    }

    public function testSupportsAttribute()
    {
        $strategy = $this->getAccessStrategy(array(
            $this->getVoterSupportsAttribute(true),
            $this->getVoterSupportsAttribute(false),
        ));

        $manager = new AccessDecisionManager($strategy);
        $this->assertTrue($manager->supportsAttribute('foo'));

        $strategy = $this->getAccessStrategy(array(
            $this->getVoterSupportsAttribute(false),
            $this->getVoterSupportsAttribute(false),
        ));

        $manager = new AccessDecisionManager($strategy);
        $this->assertFalse($manager->supportsAttribute('foo'));
    }

    public function testDecide()
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $strategy = $this->getMockAccessStrategy(true);
        $manager = new AccessDecisionManager($strategy);

        $this->assertSame(true, $manager->decide($token, array('ROLE_FOO')));

        $strategy = $this->getMockAccessStrategy(false);
        $manager = new AccessDecisionManager($strategy);

        $this->assertSame(false, $manager->decide($token, array('ROLE_FOO')));
    }

    private function getMockAccessStrategy($result)
    {
        $strategy = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\Strategy\AccessStrategy')
                 ->disableOriginalConstructor()
                 ->getMock();

        $strategy->expects($this->any())
                 ->method('decide')
                 ->will($this->returnValue($result));

        return $strategy;
    }

    private function getAccessStrategy($voters)
    {
        $strategy = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\Strategy\AccessStrategy')
                 ->disableOriginalConstructor()
                 ->getMock();

        $strategy->expects($this->any())
                 ->method('getVoters')
                 ->will($this->returnValue($voters));

        return $strategy;
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
        ;

        return $voter;
    }

    protected function getVoterSupportsClass($ret)
    {
        $voter = $this->getMock('Symfony\Component\Security\Core\Authorization\Voter\VoterInterface');
        $voter->expects($this->any())
              ->method('supportsClass')
              ->will($this->returnValue($ret));
        ;

        return $voter;
    }

    protected function getVoterSupportsAttribute($ret)
    {
        $voter = $this->getMock('Symfony\Component\Security\Core\Authorization\Voter\VoterInterface');
        $voter->expects($this->any())
              ->method('supportsAttribute')
              ->will($this->returnValue($ret));
        ;

        return $voter;
    }
}
