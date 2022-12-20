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
abstract class UserPasswordValidatorTest extends ConstraintValidatorTestCase
{
    private const PASSWORD = 's3Cr3t';
    private const SALT = '^S4lt$';

    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var PasswordHasherInterface
     */
    protected $hasher;

    /**
     * @var PasswordHasherFactoryInterface
     */
    protected $hasherFactory;

    protected function createValidator()
    {
        return new UserPasswordValidator($this->tokenStorage, $this->hasherFactory);
    }

    protected function setUp(): void
    {
        $user = $this->createUser();
        $this->tokenStorage = $this->createTokenStorage($user);
        $this->hasher = self::createMock(PasswordHasherInterface::class);
        $this->hasherFactory = $this->createHasherFactory($this->hasher);

        parent::setUp();
    }

    /**
     * @dataProvider provideConstraints
     */
    public function testPasswordIsValid(UserPassword $constraint)
    {
        $this->hasher->expects(self::once())
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
        $this->hasher->expects(self::once())
            ->method('isPasswordValid')
            ->with(static::PASSWORD, 'secret', static::SALT)
            ->willReturn(false);

        $this->validator->validate('secret', $constraint);

        $this->buildViolation('myMessage')
            ->assertRaised();
    }

    public function provideConstraints(): iterable
    {
        yield 'Doctrine style' => [new UserPassword(['message' => 'myMessage'])];

        if (\PHP_VERSION_ID >= 80000) {
            yield 'named arguments' => [eval('return new \Symfony\Component\Security\Core\Validator\Constraints\UserPassword(message: "myMessage");')];
        }
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
            ->assertRaised();
    }

    public function emptyPasswordData()
    {
        return [
            [null],
            [''],
        ];
    }

    public function testUserIsNotValid()
    {
        self::expectException(ConstraintDefinitionException::class);
        $user = new \stdClass();

        $this->tokenStorage = $this->createTokenStorage($user);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate('secret', new UserPassword());
    }

    protected function createUser()
    {
        $mock = self::createMock(UserInterface::class);

        $mock
            ->expects(self::any())
            ->method('getPassword')
            ->willReturn(static::PASSWORD)
        ;

        $mock
            ->expects(self::any())
            ->method('getSalt')
            ->willReturn(static::SALT)
        ;

        return $mock;
    }

    protected function createHasherFactory($hasher = null)
    {
        $mock = self::createMock(PasswordHasherFactoryInterface::class);

        $mock
            ->expects(self::any())
            ->method('getPasswordHasher')
            ->willReturn($hasher)
        ;

        return $mock;
    }

    protected function createTokenStorage($user = null)
    {
        $token = $this->createAuthenticationToken($user);

        $mock = self::createMock(TokenStorageInterface::class);
        $mock
            ->expects(self::any())
            ->method('getToken')
            ->willReturn($token)
        ;

        return $mock;
    }

    protected function createAuthenticationToken($user = null)
    {
        $mock = self::createMock(TokenInterface::class);
        $mock
            ->expects(self::any())
            ->method('getUser')
            ->willReturn($user)
        ;

        return $mock;
    }
}
