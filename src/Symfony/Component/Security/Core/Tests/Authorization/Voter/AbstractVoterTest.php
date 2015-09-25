<?php

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AbstractVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AbstractVoterTest_Voter extends AbstractVoter
{
    protected function voteOnAttribute($attribute, $object, TokenInterface $token)
    {
        return 'EDIT' === $attribute;
    }

    protected function supports($attribute, $class)
    {
        return $this->isClassInstanceOf($class, 'AbstractVoterTest_Object')
            && in_array($attribute, array('EDIT', 'CREATE'));
    }
}

class AbstractVoterTest extends \PHPUnit_Framework_TestCase
{
    protected $voter;
    protected $object;
    protected $token;

    protected function setUp()
    {
        $this->voter = new AbstractVoterTest_Voter();
        $this->object = $this->getMock('AbstractVoterTest_Object');
        $this->token = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
    }

    public function testAttributeAndClassSupported()
    {
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token, $this->object, array('EDIT')), 'ACCESS_GRANTED if attribute grants access');
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token, $this->object, array('CREATE')), 'ACESS_DENIED if attribute denies access');
    }

    public function testOneAttributeSupported()
    {
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token, $this->object, array('DELETE', 'EDIT')), 'ACCESS_GRANTED if supported attribute grants access');
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token, $this->object, array('DELETE', 'CREATE')), 'ACCESS_DENIED if supported attribute denies access');
    }

    public function testOneAttributeGrantsAccess()
    {
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token, $this->object, array('CREATE', 'EDIT')), 'ACCESS_GRANTED');
    }

    public function testNoAttributeSupported()
    {
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token, $this->object, array('DELETE')), 'ACCESS_ABSTAIN');
    }

    public function testClassNotSupported()
    {
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token, $this->getMock('AbstractVoterTest_Object1'), array('EDIT')), 'ACCESS_ABSTAIN');
    }

    public function testNullObject()
    {
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token, null, array('EDIT')), 'ACCESS_ABSTAIN');
    }

    public function testNoAttributes()
    {
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token, $this->object, array()), 'ACCESS_ABSTAIN');
    }
}
