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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class RefreshableRolesTest extends WebTestCase
{
    public function testRolesAreRefreshed()
    {
        // log them in!
        $client = $this->createAuthenticatedClient('cool_user');

        // refresh the page, roles have not changed yet
        $client->request('GET', '/profile');
        $rolesData = $client->getProfile()->getCollector('security')->getRoles();
        $this->assertCount(1, $rolesData);
        $this->assertEquals('ROLE_ORIGINAL', $rolesData[0]);

        // this will cause the refreshed user to have these new roles
        $client->request('GET', '/profile?new_role=ROLE_NEW');
        $rolesData = $client->getProfile()->getCollector('security')->getRoles();
        $this->assertCount(1, $rolesData);
        $this->assertEquals('ROLE_NEW', $rolesData[0]);

        // the change should be persistent
        $client->request('GET', '/profile');
        $rolesData = $client->getProfile()->getCollector('security')->getRoles();
        $this->assertCount(1, $rolesData);
        $this->assertEquals('ROLE_NEW', $rolesData[0]);
    }

    private function createAuthenticatedClient($username)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'refreshable_roles.yml'));
        $client->followRedirects(true);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = $username;
        $form['_password'] = 'test';
        $client->submit($form);

        return $client;
    }
}

class RefreshableRolesUserProvider implements UserProviderInterface
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function loadUserByUsername($username)
    {
        return new RefreshableUser($username, array('ROLE_ORIGINAL'));
    }

    public function refreshUser(UserInterface $user)
    {
        $request = $this->requestStack->getCurrentRequest();
        // a sneaky way of faking the stored user's roles being changed
        if ($request->query->has('new_role')) {
            $user->setRoles(array($request->query->get('new_role')));
        }

        return $user;
    }

    public function supportsClass($class)
    {
        return RefreshableUser::class === $class;
    }
}

class RefreshableUser implements UserInterface
{
    private $username;
    private $roles;

    public function __construct($username, array $roles)
    {
        $this->username = $username;
        $this->roles = $roles;
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getRoles()
    {
        return $this->roles;
    }

    public function setRoles($roles)
    {
        $this->roles = $roles;
    }

    public function getPassword()
    {
        return 'test';
    }

    public function getSalt()
    {
    }

    public function eraseCredentials()
    {
    }
}
