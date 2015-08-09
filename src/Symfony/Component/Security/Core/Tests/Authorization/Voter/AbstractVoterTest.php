<?php

namespace Symfony\Component\Security\Core\Tests\Authorization\Voter;

use Symfony\Component\Security\Core\Authorization\Voter\AbstractVoter;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AbstractVoterTest_Voter extends AbstractVoter
{
    protected function isGranted($attribute, $object, $user = null)
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
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token, $this->object, array('EDIT')));
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token, $this->object, array('CREATE')));
    }

    public function testAttributeNotSupported()
    {
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token, $this->object, array('DELETE')));
    }

    public function testOneAttributeSupported()
    {
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($this->token, $this->object, array('DELETE', 'EDIT')));
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($this->token, $this->object, array('DELETE', 'CREATE')));
    }

    public function testClassNotSupported()
    {
        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($this->token, $this->getMock('AbstractVoterTest_Object1'), array('EDIT')));
    }
}
