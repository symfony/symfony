<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Component\Security\Core\User\User;

class SecurityTest extends AbstractWebTestCase
{
    /**
     * @dataProvider getUsers
     */
    public function testLoginUser(string $username, array $roles, ?string $firewallContext)
    {
        $user = new User($username, 'the-password', $roles);
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);

        if (null === $firewallContext) {
            $client->loginUser($user);
        } else {
            $client->loginUser($user, $firewallContext);
        }

        $client->request('GET', '/'.($firewallContext ?? 'main').'/user_profile');
        $this->assertEquals('Welcome '.$username.'!', $client->getResponse()->getContent());
    }

    public function getUsers()
    {
        yield ['the-username', ['ROLE_FOO'], null];
        yield ['the-username', ['ROLE_FOO'], 'main'];
        yield ['other-username', ['ROLE_FOO'], 'custom'];

        yield ['the-username', ['ROLE_FOO'], null];
        yield ['no-role-username', [], null];
    }

    public function testLoginUserMultipleRequests()
    {
        $user = new User('the-username', 'the-password', ['ROLE_FOO']);
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);
        $client->loginUser($user);

        $client->request('GET', '/main/user_profile');
        $this->assertEquals('Welcome the-username!', $client->getResponse()->getContent());

        $client->request('GET', '/main/user_profile');
        $this->assertEquals('Welcome the-username!', $client->getResponse()->getContent());
    }

    public function testLoginInBetweenRequests()
    {
        $user = new User('the-username', 'the-password', ['ROLE_FOO']);
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);

        $client->request('GET', '/main/user_profile');
        $this->assertTrue($client->getResponse()->isRedirect('http://localhost/login'));

        $client->loginUser($user);

        $client->request('GET', '/main/user_profile');
        $this->assertEquals('Welcome the-username!', $client->getResponse()->getContent());
    }
}
