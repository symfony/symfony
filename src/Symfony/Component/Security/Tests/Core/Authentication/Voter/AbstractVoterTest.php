<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Tests\Core\Authentication\Voter;

use Symfony\Component\Security\Core\Authorization\Voter\AbstractVoter;

/**
 * @author Roman Marintšenko <inoryy@gmail.com>
 */
class AbstractVoterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AbstractVoter
     */
    private $voter;

    private $token;

    protected function setUp()
    {
        $this->voter = new VoterFixture();

        $tokenMock = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $tokenMock
            ->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue('user'));

        $this->token = $tokenMock;
    }

    /**
     * @dataProvider getData
     */
    public function testVote($expectedVote, $object, $attributes, $message)
    {
        $this->assertEquals($expectedVote, $this->voter->vote($this->token, $object, $attributes), $message);
    }

    public function getData()
    {
        return array(
            array(AbstractVoter::ACCESS_ABSTAIN, null, array(), 'ACCESS_ABSTAIN for null objects'),
            array(AbstractVoter::ACCESS_ABSTAIN, new UnsupportedObjectFixture(), array(), 'ACCESS_ABSTAIN for objects with unsupported class'),
            array(AbstractVoter::ACCESS_ABSTAIN, new ObjectFixture(), array(), 'ACCESS_ABSTAIN for no attributes'),
            array(AbstractVoter::ACCESS_ABSTAIN, new ObjectFixture(), array('foobar'), 'ACCESS_ABSTAIN for unsupported attributes'),
            array(AbstractVoter::ACCESS_GRANTED, new ObjectFixture(), array('foo'), 'ACCESS_GRANTED if attribute grants access'),
            array(AbstractVoter::ACCESS_GRANTED, new ObjectFixture(), array('bar', 'foo'), 'ACCESS_GRANTED if *at least one* attribute grants access'),
            array(AbstractVoter::ACCESS_GRANTED, new ObjectFixture(), array('foobar', 'foo'), 'ACCESS_GRANTED if *at least one* attribute grants access'),
            array(AbstractVoter::ACCESS_DENIED, new ObjectFixture(), array('bar', 'baz'), 'ACCESS_DENIED for if no attribute grants access'),
        );
    }
}

class VoterFixture extends AbstractVoter
{
    protected function getSupportedClasses()
    {
        return array(
            'Symfony\Component\Security\Tests\Core\Authentication\Voter\ObjectFixture',
        );
    }

    protected function getSupportedAttributes()
    {
        return array('foo', 'bar', 'baz');
    }

    protected function isGranted($attribute, $object, $user = null)
    {
        return $attribute === 'foo';
    }
}

class ObjectFixture
{
}

class UnsupportedObjectFixture
{
}
