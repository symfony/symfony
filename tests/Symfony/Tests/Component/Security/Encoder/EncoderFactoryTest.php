<?php

namespace Symfony\Tests\Component\Security\Encoder;

use Symfony\Component\Security\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Encoder\EncoderFactory;

class EncoderFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetEncoderWithMessageDigestEncoder()
    {
        $factory = new EncoderFactory(array('Symfony\Component\Security\User\AccountInterface' => array(
            'class' => 'Symfony\Component\Security\Encoder\MessageDigestPasswordEncoder',
            'arguments' => array('sha512', true, 5),
        )));

        $encoder = $factory->getEncoder($this->getMock('Symfony\Component\Security\User\AccountInterface'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha512', true, 5);

        $this->assertEquals($expectedEncoder->encodePassword('foo', 'moo'), $encoder->encodePassword('foo', 'moo'));
    }

    public function testGetEncoderWithService()
    {
        $factory = new EncoderFactory(array());
        $factory->addEncoder('Symfony\Component\Security\User\AccountInterface', new MessageDigestPasswordEncoder('sha1'));

        $encoder = $factory->getEncoder($this->getMock('Symfony\Component\Security\User\AccountInterface'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');

        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }
}