<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Tests\Authorization\Voter;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Authorization\Voter\Voter;
use Symphony\Component\Security\Core\Authorization\Voter\VoterInterface;

class VoterTest extends TestCase
{
    protected $token;

    protected function setUp()
    {
        $this->token = $this->getMockBuilder('Symphony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
    }

    public function getTests()
    {
        return array(
            array(array('EDIT'), VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if attribute and class are supported and attribute grants access'),
            array(array('CREATE'), VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if attribute and class are supported and attribute does not grant access'),

            array(array('DELETE', 'EDIT'), VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute is supported and grants access'),
            array(array('DELETE', 'CREATE'), VoterInterface::ACCESS_DENIED, new \stdClass(), 'ACCESS_DENIED if one attribute is supported and denies access'),

            array(array('CREATE', 'EDIT'), VoterInterface::ACCESS_GRANTED, new \stdClass(), 'ACCESS_GRANTED if one attribute grants access'),

            array(array('DELETE'), VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attribute is supported'),

            array(array('EDIT'), VoterInterface::ACCESS_ABSTAIN, $this, 'ACCESS_ABSTAIN if class is not supported'),

            array(array('EDIT'), VoterInterface::ACCESS_ABSTAIN, null, 'ACCESS_ABSTAIN if object is null'),

            array(array(), VoterInterface::ACCESS_ABSTAIN, new \stdClass(), 'ACCESS_ABSTAIN if no attributes were provided'),
        );
    }

    /**
     * @dataProvider getTests
     */
    public function testVote(array $attributes, $expectedVote, $object, $message)
    {
        $voter = new VoterTest_Voter();

        $this->assertEquals($expectedVote, $voter->vote($this->token, $object, $attributes), $message);
    }
}

class VoterTest_Voter extends Voter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token)
    {
        return 'EDIT' === $attribute;
    }

    protected function supports($attribute, $object)
    {
        return $object instanceof \stdClass && in_array($attribute, array('EDIT', 'CREATE'));
    }
}
