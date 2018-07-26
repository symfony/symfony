<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Encoder\EncoderAwareInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class EncoderFactoryTest extends TestCase
{
    public function testGetEncoderWithMessageDigestEncoder()
    {
        $factory = new EncoderFactory(array('Symfony\Component\Security\Core\User\UserInterface' => array(
            'class' => 'Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder',
            'arguments' => array('sha512', true, 5),
        )));

        $encoder = $factory->getEncoder($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock());
        $expectedEncoder = new MessageDigestPasswordEncoder('sha512', true, 5);

        $this->assertEquals($expectedEncoder->encodePassword('foo', 'moo'), $encoder->encodePassword('foo', 'moo'));
    }

    public function testGetEncoderWithService()
    {
        $factory = new EncoderFactory(array(
            'Symfony\Component\Security\Core\User\UserInterface' => new MessageDigestPasswordEncoder('sha1'),
        ));

        $encoder = $factory->getEncoder($this->getMockBuilder('Symfony\Component\Security\Core\User\UserInterface')->getMock());
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));

        $encoder = $factory->getEncoder(new User('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetEncoderWithClassName()
    {
        $factory = new EncoderFactory(array(
            'Symfony\Component\Security\Core\User\UserInterface' => new MessageDigestPasswordEncoder('sha1'),
        ));

        $encoder = $factory->getEncoder('Symfony\Component\Security\Core\Tests\Encoder\SomeChildUser');
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetEncoderConfiguredForConcreteClassWithService()
    {
        $factory = new EncoderFactory(array(
            'Symfony\Component\Security\Core\User\User' => new MessageDigestPasswordEncoder('sha1'),
        ));

        $encoder = $factory->getEncoder(new User('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetEncoderConfiguredForConcreteClassWithClassName()
    {
        $factory = new EncoderFactory(array(
            'Symfony\Component\Security\Core\Tests\Encoder\SomeUser' => new MessageDigestPasswordEncoder('sha1'),
        ));

        $encoder = $factory->getEncoder('Symfony\Component\Security\Core\Tests\Encoder\SomeChildUser');
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetNamedEncoderForEncoderAware()
    {
        $factory = new EncoderFactory(array(
            'Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser' => new MessageDigestPasswordEncoder('sha256'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha1'),
        ));

        $encoder = $factory->getEncoder(new EncAwareUser('user', 'pass'));
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    public function testGetNullNamedEncoderForEncoderAware()
    {
        $factory = new EncoderFactory(array(
            'Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser' => new MessageDigestPasswordEncoder('sha1'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha256'),
        ));

        $user = new EncAwareUser('user', 'pass');
        $user->encoderName = null;
        $encoder = $factory->getEncoder($user);
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testGetInvalidNamedEncoderForEncoderAware()
    {
        $factory = new EncoderFactory(array(
            'Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser' => new MessageDigestPasswordEncoder('sha1'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha256'),
        ));

        $user = new EncAwareUser('user', 'pass');
        $user->encoderName = 'invalid_encoder_name';
        $encoder = $factory->getEncoder($user);
    }

    public function testGetEncoderForEncoderAwareWithClassName()
    {
        $factory = new EncoderFactory(array(
            'Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser' => new MessageDigestPasswordEncoder('sha1'),
            'encoder_name' => new MessageDigestPasswordEncoder('sha256'),
        ));

        $encoder = $factory->getEncoder('Symfony\Component\Security\Core\Tests\Encoder\EncAwareUser');
        $expectedEncoder = new MessageDigestPasswordEncoder('sha1');
        $this->assertEquals($expectedEncoder->encodePassword('foo', ''), $encoder->encodePassword('foo', ''));
    }
}

class SomeUser implements UserInterface
{
    public function getRoles()
    {
    }

    public function getPassword()
    {
    }

    public function getSalt()
    {
    }

    public function getUsername()
    {
    }

    public function eraseCredentials()
    {
    }
}

class SomeChildUser extends SomeUser
{
}

class EncAwareUser extends SomeUser implements EncoderAwareInterface
{
    public $encoderName = 'encoder_name';

    public function getEncoderName()
    {
        return $this->encoderName;
    }
}
