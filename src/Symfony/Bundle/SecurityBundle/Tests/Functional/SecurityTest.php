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
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityTest extends AbstractWebTestCase
{
    public function testServiceIsFunctional()
    {
        $kernel = self::createKernel(['test_case' => 'SecurityHelper', 'root_config' => 'config.yml']);
        $kernel->boot();
        $container = $kernel->getContainer();

        // put a token into the storage so the final calls can function
        $user = new User('foo', 'pass');
        $token = new UsernamePasswordToken($user, '', 'provider', ['ROLE_USER']);
        $container->get('security.token_storage')->setToken($token);

        $security = $container->get('functional_test.security.helper');
        $this->assertTrue($security->isGranted('ROLE_USER'));
        $this->assertSame($token, $security->getToken());
    }

    public function userWillBeMarkedAsChangedIfRolesHasChangedProvider()
    {
        return [
            [
                new User('user1', 'test', ['ROLE_ADMIN']),
                new User('user1', 'test', ['ROLE_USER']),
            ],
            [
                new UserWithoutEquatable('user1', 'test', ['ROLE_ADMIN']),
                new UserWithoutEquatable('user1', 'test', ['ROLE_USER']),
            ],
        ];
    }

    /**
     * @dataProvider userWillBeMarkedAsChangedIfRolesHasChangedProvider
     */
    public function testUserWillBeMarkedAsChangedIfRolesHasChanged(UserInterface $userWithAdminRole, UserInterface $userWithoutAdminRole)
    {
        $client = $this->createClient(['test_case' => 'AbstractTokenCompareRoles', 'root_config' => 'config.yml']);
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
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // updating user provider with same user but revoked ROLE_ADMIN from user1
        $userProvider->setUser('user1', $userWithoutAdminRole);

        // user1 has lost ROLE_ADMIN and MUST be redirected away from secure page
        $client->request('GET', '/admin');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }
}

final class UserWithoutEquatable implements UserInterface
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
        return $this->getUsername();
    }

    /**
     * {@inheritdoc}
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * {@inheritdoc}
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * {@inheritdoc}
     */
    public function getSalt()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * {@inheritdoc}
     */
    public function isAccountNonExpired()
    {
        return $this->accountNonExpired;
    }

    /**
     * {@inheritdoc}
     */
    public function isAccountNonLocked()
    {
        return $this->accountNonLocked;
    }

    /**
     * {@inheritdoc}
     */
    public function isCredentialsNonExpired()
    {
        return $this->credentialsNonExpired;
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
    }
}
