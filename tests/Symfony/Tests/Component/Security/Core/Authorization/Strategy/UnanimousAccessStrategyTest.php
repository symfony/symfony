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

use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousAccessStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class UnanimousAccessStrategyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getDecisions
     */
    public function testDecide($voters, $allowIfAllAbstainDecisions, $expected)
    {
        $token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');

        $strategy = new UnanimousAccessStrategy($voters, $allowIfAllAbstainDecisions);

        $this->assertSame($expected, $strategy->decide($token, array('ROLE_FOO')));
    }

    public function getDecisions()
    {
        return array(
            array($this->getVoters(1, 0, 0), false, true),
            array($this->getVoters(1, 0, 1), false, true),
            array($this->getVoters(1, 1, 0), false, false),

            array($this->getVoters(0, 0, 2), false, false),
            array($this->getVoters(0, 0, 2), true, true),
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
        ;

        return $voter;
    }
}