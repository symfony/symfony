<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Signature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Signature\Exception\ExpiredSignatureException;
use Symfony\Component\Security\Core\Signature\Exception\InvalidSignatureException;
use Symfony\Component\Security\Core\Signature\SignatureHasher;
use Symfony\Component\Security\Core\User\UserInterface;

class SignatureHasherTest extends TestCase
{
    public function testComputeSignatureHash()
    {
        $user = new TestSignatureUser('john', 'my-password');
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['password'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
    }

    public function testVerifySignatureHashValid()
    {
        $user = new TestSignatureUser('john', 'my-password');
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['password'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $hasher->verifySignatureHash($user, $expires, $hash);

        $this->addToAssertionCount(1);
    }

    public function testVerifySignatureHashExpired()
    {
        $user = new TestSignatureUser('john', 'my-password');
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['password'], 'secret');

        $expires = time() - 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $this->expectException(ExpiredSignatureException::class);
        $hasher->verifySignatureHash($user, $expires, $hash);
    }

    public function testVerifySignatureHashInvalid()
    {
        $user = new TestSignatureUser('john', 'my-password');
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['password'], 'secret');

        $expires = time() + 3600;

        $this->expectException(InvalidSignatureException::class);
        $hasher->verifySignatureHash($user, $expires, 'invalid-hash');
    }

    public function testVerifySignatureHashChangedPropertyInvalidates()
    {
        $user = new TestSignatureUser('john', 'my-password');
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['password'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $userWithNewPassword = new TestSignatureUser('john', 'new-password');

        $this->expectException(InvalidSignatureException::class);
        $hasher->verifySignatureHash($userWithNewPassword, $expires, $hash);
    }

    public function testComputeSignatureHashWithDateTimeProperty()
    {
        $date = new \DateTimeImmutable('2024-01-15 10:30:00');
        $user = new TestSignatureUserWithDate('john', $date);
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['createdAt'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $hasher->verifySignatureHash($user, $expires, $hash);

        $this->addToAssertionCount(1);
    }

    public function testComputeSignatureHashWithEnumProperty()
    {
        $user = new TestSignatureUserWithEnum('john', TestUserStatus::Active);
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['status'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $hasher->verifySignatureHash($user, $expires, $hash);

        $this->addToAssertionCount(1);
    }

    public function testComputeSignatureHashWithBackedEnumProperty()
    {
        $user = new TestSignatureUserWithBackedEnum('john', TestUserRole::Admin);
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['role'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $hasher->verifySignatureHash($user, $expires, $hash);

        $this->addToAssertionCount(1);
    }

    public function testEnumPropertyChangeInvalidatesSignature()
    {
        $user = new TestSignatureUserWithEnum('john', TestUserStatus::Active);
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['status'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $userWithNewStatus = new TestSignatureUserWithEnum('john', TestUserStatus::Inactive);

        $this->expectException(InvalidSignatureException::class);
        $hasher->verifySignatureHash($userWithNewStatus, $expires, $hash);
    }

    public function testBackedEnumPropertyChangeInvalidatesSignature()
    {
        $user = new TestSignatureUserWithBackedEnum('john', TestUserRole::Admin);
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['role'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $userWithNewRole = new TestSignatureUserWithBackedEnum('john', TestUserRole::User);

        $this->expectException(InvalidSignatureException::class);
        $hasher->verifySignatureHash($userWithNewRole, $expires, $hash);
    }

    public function testComputeSignatureHashWithStringableProperty()
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };
        $user = new TestSignatureUserWithStringable('john', $stringable);
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['data'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $hasher->verifySignatureHash($user, $expires, $hash);

        $this->addToAssertionCount(1);
    }

    public function testComputeSignatureHashWithNullProperty()
    {
        $user = new TestSignatureUserWithNullable('john', null);
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['optionalValue'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $hasher->verifySignatureHash($user, $expires, $hash);

        $this->addToAssertionCount(1);
    }

    public function testComputeSignatureHashWithInvalidPropertyThrows()
    {
        $user = new TestSignatureUserWithArray('john', ['invalid']);
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['data'], 'secret');

        $expires = time() + 3600;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must return a value that can be cast to a string');
        $hasher->computeSignatureHash($user, $expires);
    }

    public function testAcceptSignatureHashValid()
    {
        $user = new TestSignatureUser('john', 'my-password');
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), ['password'], 'secret');

        $expires = time() + 3600;
        $hash = $hasher->computeSignatureHash($user, $expires);

        $hasher->acceptSignatureHash('john', $expires, $hash);

        $this->addToAssertionCount(1);
    }

    public function testAcceptSignatureHashExpired()
    {
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), [], 'secret');

        $expires = time() - 3600;

        $this->expectException(ExpiredSignatureException::class);
        $this->expectExceptionMessage('Signature has expired.');
        $hasher->acceptSignatureHash('john', $expires, 'any-hash');
    }

    public function testAcceptSignatureHashInvalid()
    {
        $hasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), [], 'secret');

        $expires = time() + 3600;

        $this->expectException(InvalidSignatureException::class);
        $hasher->acceptSignatureHash('john', $expires, 'invalid-hash');
    }

    public function testConstructorThrowsOnEmptySecret()
    {
        $this->expectException(\Symfony\Component\Security\Core\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-empty secret is required.');

        new SignatureHasher(PropertyAccess::createPropertyAccessor(), [], '');
    }
}

enum TestUserStatus
{
    case Active;
    case Inactive;
    case Pending;
}

enum TestUserRole: string
{
    case Admin = 'admin';
    case User = 'user';
    case Guest = 'guest';
}

class TestSignatureUser implements UserInterface
{
    public function __construct(
        private string $username,
        private string $password,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}

class TestSignatureUserWithDate implements UserInterface
{
    public function __construct(
        private string $username,
        private \DateTimeInterface $createdAt,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}

class TestSignatureUserWithEnum implements UserInterface
{
    public function __construct(
        private string $username,
        private TestUserStatus $status,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getStatus(): TestUserStatus
    {
        return $this->status;
    }
}

class TestSignatureUserWithBackedEnum implements UserInterface
{
    public function __construct(
        private string $username,
        private TestUserRole $role,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getRole(): TestUserRole
    {
        return $this->role;
    }
}

class TestSignatureUserWithStringable implements UserInterface
{
    public function __construct(
        private string $username,
        private \Stringable $data,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getData(): \Stringable
    {
        return $this->data;
    }
}

class TestSignatureUserWithNullable implements UserInterface
{
    public function __construct(
        private string $username,
        private ?string $optionalValue,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getOptionalValue(): ?string
    {
        return $this->optionalValue;
    }
}

class TestSignatureUserWithArray implements UserInterface
{
    public function __construct(
        private string $username,
        private array $data,
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function getData(): array
    {
        return $this->data;
    }
}
