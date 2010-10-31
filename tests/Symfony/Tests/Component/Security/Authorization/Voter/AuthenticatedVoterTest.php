<?php

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Security\Authorization\Voter;

use Symfony\Component\Security\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Role\Role;

class AuthenticatedVoterTest extends \PHPUnit_Framework_TestCase
{
    public function testSupportsClass()
    {
        $voter = new AuthenticatedVoter();
        $this->assertTrue($voter->supportsClass('stdClass'));
    }

    /**
     * @dataProvider getVoteTests
     */
    public function testVote($authenticated, $attributes, $expected)
    {
        $voter = new AuthenticatedVoter();

        $this->assertSame($expected, $voter->vote($this->getToken($authenticated), null, $attributes));
    }

    public function getVoteTests()
    {
        return array(
            array(true, array(), VoterInterface::ACCESS_ABSTAIN),
            array(true, array('FOO'), VoterInterface::ACCESS_ABSTAIN),
            array(false, array(), VoterInterface::ACCESS_ABSTAIN),
            array(false, array('FOO'), VoterInterface::ACCESS_ABSTAIN),

            array(true, array('IS_AUTHENTICATED_ANONYMOUSLY'), VoterInterface::ACCESS_GRANTED),
            array(false, array('IS_AUTHENTICATED_ANONYMOUSLY'), VoterInterface::ACCESS_GRANTED),

            array(true, array('IS_AUTHENTICATED_FULLY'), VoterInterface::ACCESS_GRANTED),
            array(false, array('IS_AUTHENTICATED_FULLY'), VoterInterface::ACCESS_DENIED),
        );
    }

    protected function getToken($authenticated)
    {
        if ($authenticated) {
            return $this->getMock('Symfony\Component\Security\Authentication\Token\TokenInterface');
        } else {
            return $this->getMock('Symfony\Component\Security\Authentication\Token\AnonymousToken', null, array('', ''));
        }
    }
}
