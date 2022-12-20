<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\SecuredPageBundle\Security\Core\User\ArrayUserProvider;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityTest extends AbstractWebTestCase
{
    public function testServiceIsFunctional()
    {
        $kernel = self::createKernel(['test_case' => 'SecurityHelper', 'root_config' => 'config.yml']);
        $kernel->boot();
        $container = $kernel->getContainer();

        // put a token into the storage so the final calls can function
        $user = new InMemoryUser('foo', 'pass');
        $token = new UsernamePasswordToken($user, 'provider', ['ROLE_USER']);
        $container->get('functional.test.security.token_storage')->setToken($token);

        $security = $container->get('functional_test.security.helper');
        self::assertTrue($security->isGranted('ROLE_USER'));
        self::assertSame($token, $security->getToken());
    }

    /**
     * @dataProvider userWillBeMarkedAsChangedIfRolesHasChangedProvider
     */
    public function testUserWillBeMarkedAsChangedIfRolesHasChanged(UserInterface $userWithAdminRole, UserInterface $userWithoutAdminRole)
    {
        $client = self::createClient(['test_case' => 'AbstractTokenCompareRoles', 'root_config' => 'config.yml']);
        $client->disableReboot();

        /** @var ArrayUserProvider $userProvider */
        $userProvider = static::$kernel->getContainer()->get('security.user.provider.array');
        $userProvider->addUser($userWithAdminRole);

        $client->request('POST', '/login', [
            '_username' => 'user1',
            '_password' => 'test',
        ]);

        // user1 has ROLE_ADMIN and can visit secure page
        $client->request('GET', '/admin');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // updating user provider with same user but revoked ROLE_ADMIN from user1
        $userProvider->setUser('user1', $userWithoutAdminRole);

        // user1 has lost ROLE_ADMIN and MUST be redirected away from secure page
        $client->request('GET', '/admin');
        self::assertEquals(302, $client->getResponse()->getStatusCode());
    }

    /**
     * @dataProvider userWillBeMarkedAsChangedIfRolesHasChangedProvider
     * @group legacy
     */
    public function testLegacyUserWillBeMarkedAsChangedIfRolesHasChanged(UserInterface $userWithAdminRole, UserInterface $userWithoutAdminRole)
    {
        $client = self::createClient(['test_case' => 'AbstractTokenCompareRoles', 'root_config' => 'legacy_config.yml']);
        $client->disableReboot();

        /** @var ArrayUserProvider $userProvider */
        $userProvider = static::$kernel->getContainer()->get('security.user.provider.array');
        $userProvider->addUser($userWithAdminRole);

        $client->request('POST', '/login', [
            '_username' => 'user1',
            '_password' => 'test',
        ]);

        // user1 has ROLE_ADMIN and can visit secure page
        $client->request('GET', '/admin');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // updating user provider with same user but revoked ROLE_ADMIN from user1
        $userProvider->setUser('user1', $userWithoutAdminRole);

        // user1 has lost ROLE_ADMIN and MUST be redirected away from secure page
        $client->request('GET', '/admin');
        self::assertEquals(302, $client->getResponse()->getStatusCode());
    }

    /**
     * @group legacy
     */
    public function testLegacyServiceIsFunctional()
    {
        $kernel = self::createKernel(['test_case' => 'SecurityHelper', 'root_config' => 'legacy_config.yml']);
        $kernel->boot();
        $container = $kernel->getContainer();

        // put a token into the storage so the final calls can function
        $user = new InMemoryUser('foo', 'pass');
        $token = new UsernamePasswordToken($user, 'provider', ['ROLE_USER']);
        $container->get('functional.test.security.token_storage')->setToken($token);

        $security = $container->get('functional_test.security.helper');
        self::assertTrue($security->isGranted('ROLE_USER'));
        self::assertSame($token, $security->getToken());
    }

    public function userWillBeMarkedAsChangedIfRolesHasChangedProvider()
    {
        return [
            [
                new InMemoryUser('user1', 'test', ['ROLE_ADMIN']),
                new InMemoryUser('user1', 'test', ['ROLE_USER']),
            ],
            [
                new UserWithoutEquatable('user1', 'test', ['ROLE_ADMIN']),
                new UserWithoutEquatable('user1', 'test', ['ROLE_USER']),
            ],
        ];
    }
}

final class UserWithoutEquatable implements UserInterface, PasswordAuthenticatedUserInterface
{
    private $username;
    private $password;
    private $enabled;
    private $accountNonExpired;
    private $credentialsNonExpired;
    private $accountNonLocked;
    private $roles;

    public function __construct(?string $username, ?string $password, array $roles = [], bool $enabled = true, bool $userNonExpired = true, bool $credentialsNonExpired = true, bool $userNonLocked = true)
    {
        if ('' === $username || null === $username) {
            throw new \InvalidArgumentException('The username cannot be empty.');
        }

        $this->username = $username;
        $this->password = $password;
        $this->enabled = $enabled;
        $this->accountNonExpired = $userNonExpired;
        $this->credentialsNonExpired = $credentialsNonExpired;
        $this->accountNonLocked = $userNonLocked;
        $this->roles = $roles;
    }

    public function __toString()
    {
        return $this->getUserIdentifier();
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function getSalt(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function isAccountNonExpired(): bool
    {
        return $this->accountNonExpired;
    }

    /**
     * {@inheritdoc}
     */
    public function isAccountNonLocked(): bool
    {
        return $this->accountNonLocked;
    }

    /**
     * {@inheritdoc}
     */
    public function isCredentialsNonExpired(): bool
    {
        return $this->credentialsNonExpired;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials(): void
    {
    }
}
