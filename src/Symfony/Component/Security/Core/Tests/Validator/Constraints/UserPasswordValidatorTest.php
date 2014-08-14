<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Validator\Constraints;

use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;
use Symfony\Component\Validator\Tests\Constraints\AbstractConstraintValidatorTest;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UserPasswordValidatorTest extends AbstractConstraintValidatorTest
{
    const PASSWORD = 's3Cr3t';

    const SALT = '^S4lt$';

    /**
     * @var SecurityContextInterface
     */
    protected $securityContext;

    /**
     * @var PasswordEncoderInterface
     */
    protected $encoder;

    /**
     * @var EncoderFactoryInterface
     */
    protected $encoderFactory;

    protected function createValidator()
    {
        return new UserPasswordValidator($this->securityContext, $this->encoderFactory);
    }

    protected function setUp()
    {
        $user = $this->createUser();
        $this->securityContext = $this->createSecurityContext($user);
        $this->encoder = $this->createPasswordEncoder();
        $this->encoderFactory = $this->createEncoderFactory($this->encoder);

        parent::setUp();
    }

    public function testPasswordIsValid()
    {
        $constraint = new UserPassword(array(
            'message' => 'myMessage',
        ));

        $this->encoder->expects($this->once())
            ->method('isPasswordValid')
            ->with(static::PASSWORD, 'secret', static::SALT)
            ->will($this->returnValue(true));

        $this->validator->validate('secret', $constraint);

        $this->assertNoViolation();
    }

    public function testPasswordIsNotValid()
    {
        $constraint = new UserPassword(array(
            'message' => 'myMessage',
        ));

        $this->encoder->expects($this->once())
            ->method('isPasswordValid')
            ->with(static::PASSWORD, 'secret', static::SALT)
            ->will($this->returnValue(false));

        $this->validator->validate('secret', $constraint);

        $this->assertViolation('myMessage');
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testUserIsNotValid()
    {
        $user = $this->getMock('Foo\Bar\User');

        $this->securityContext = $this->createSecurityContext($user);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate('secret', new UserPassword());
    }

    protected function createUser()
    {
        $mock = $this->getMock('Symfony\Component\Security\Core\User\UserInterface');

        $mock
            ->expects($this->any())
            ->method('getPassword')
            ->will($this->returnValue(static::PASSWORD))
        ;

        $mock
            ->expects($this->any())
            ->method('getSalt')
            ->will($this->returnValue(static::SALT))
        ;

        return $mock;
    }

    protected function createPasswordEncoder($isPasswordValid = true)
    {
        return $this->getMock('Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface');
    }

    protected function createEncoderFactory($encoder = null)
    {
        $mock = $this->getMock('Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface');

        $mock
            ->expects($this->any())
            ->method('getEncoder')
            ->will($this->returnValue($encoder))
        ;

        return $mock;
    }

    protected function createSecurityContext($user = null)
    {
        $token = $this->createAuthenticationToken($user);

        $mock = $this->getMock('Symfony\Component\Security\Core\SecurityContextInterface');
        $mock
            ->expects($this->any())
            ->method('getToken')
            ->will($this->returnValue($token))
        ;

        return $mock;
    }

    protected function createAuthenticationToken($user = null)
    {
        $mock = $this->getMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $mock
            ->expects($this->any())
            ->method('getUser')
            ->will($this->returnValue($user))
        ;

        return $mock;
    }
}
