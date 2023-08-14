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

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class UserPasswordValidatorTestCase extends ConstraintValidatorTestCase
{
    private const PASSWORD = 's3Cr3t';
    private const SALT = '^S4lt$';

    protected TokenStorageInterface $tokenStorage;
    protected PasswordHasherInterface $hasher;
    protected PasswordHasherFactoryInterface $hasherFactory;

    protected function createValidator(): UserPasswordValidator
    {
        return new UserPasswordValidator($this->tokenStorage, $this->hasherFactory);
    }

    protected function setUp(): void
    {
        $user = $this->createUser();
        $this->tokenStorage = $this->createTokenStorage($user);
        $this->hasher = $this->createMock(PasswordHasherInterface::class);
        $this->hasherFactory = $this->createHasherFactory($this->hasher);

        parent::setUp();
    }

    /**
     * @dataProvider provideConstraints
     */
    public function testPasswordIsValid(UserPassword $constraint)
    {
        $this->hasher->expects($this->once())
            ->method('isPasswordValid')
            ->with(static::PASSWORD, 'secret', static::SALT)
            ->willReturn(true);

        $this->validator->validate('secret', $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider provideConstraints
     */
    public function testPasswordIsNotValid(UserPassword $constraint)
    {
        $this->hasher->expects($this->once())
            ->method('isPasswordValid')
            ->with(static::PASSWORD, 'secret', static::SALT)
            ->willReturn(false);

        $this->validator->validate('secret', $constraint);

        $this->buildViolation('myMessage')
            ->setCode(UserPassword::INVALID_PASSWORD_ERROR)
            ->assertRaised();
    }

    public static function provideConstraints(): iterable
    {
        yield 'Doctrine style' => [new UserPassword(['message' => 'myMessage'])];

        yield 'named arguments' => [new UserPassword(message: 'myMessage')];
    }

    /**
     * @dataProvider emptyPasswordData
     */
    public function testEmptyPasswordsAreNotValid($password)
    {
        $constraint = new UserPassword([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($password, $constraint);

        $this->buildViolation('myMessage')
            ->setCode(UserPassword::INVALID_PASSWORD_ERROR)
            ->assertRaised();
    }

    public static function emptyPasswordData()
    {
        return [
            [null],
            [''],
        ];
    }

    public function testUserIsNotValid()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $user = new \stdClass();

        $this->tokenStorage = $this->createTokenStorage($user);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate('secret', new UserPassword());
    }

    protected function createUser()
    {
        $mock = $this->createMock(UserInterface::class);

        $mock
            ->expects($this->any())
            ->method('getPassword')
            ->willReturn(static::PASSWORD)
        ;

        $mock
            ->expects($this->any())
            ->method('getSalt')
            ->willReturn(static::SALT)
        ;

        return $mock;
    }

    protected function createHasherFactory($hasher = null)
    {
        $mock = $this->createMock(PasswordHasherFactoryInterface::class);

        $mock
            ->expects($this->any())
            ->method('getPasswordHasher')
            ->willReturn($hasher)
        ;

        return $mock;
    }

    protected function createTokenStorage($user = null)
    {
        $token = $this->createAuthenticationToken($user);

        $mock = $this->createMock(TokenStorageInterface::class);
        $mock
            ->expects($this->any())
            ->method('getToken')
            ->willReturn($token)
        ;

        return $mock;
    }

    protected function createAuthenticationToken($user = null)
    {
        $mock = $this->createMock(TokenInterface::class);
        $mock
            ->expects($this->any())
            ->method('getUser')
            ->willReturn($user)
        ;

        return $mock;
    }
}
