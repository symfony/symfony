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

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\User;

class SecurityTest extends AbstractWebTestCase
{
    /**
     * @dataProvider getUsers
     */
    public function testLoginUser(string $username, ?string $password, array $roles, ?string $firewallContext, string $expectedProviderKey)
    {
        $user = new User($username, $password, $roles);
        $client = $this->createClient(['test_case' => 'Security', 'root_config' => 'config.yml']);

        if (null === $firewallContext) {
            $client->loginUser($user);
        } else {
            $client->loginUser($user, $firewallContext);
        }

        /** @var SessionInterface $session */
        $session = $client->getContainer()->get('session');
        /** @var UsernamePasswordToken $userToken */
        $userToken = unserialize($session->get('_security_'.$expectedProviderKey));

        $this->assertSame('_security_'.$expectedProviderKey, array_keys($session->all())[0]);
        $this->assertSame($expectedProviderKey, $userToken->getProviderKey());
        $this->assertSame($username, $userToken->getUsername());
        $this->assertSame($password, $userToken->getUser()->getPassword());
        $this->assertSame($roles, $userToken->getUser()->getRoles());

        $this->assertNotNull($client->getCookieJar()->get('MOCKSESSID'));
    }

    public function getUsers()
    {
        yield ['the-username', 'the-password', ['ROLE_FOO'], null, 'main'];
        yield ['the-username', 'the-password', ['ROLE_FOO'], 'main', 'main'];
        yield ['the-username', 'the-password', ['ROLE_FOO'], 'custom_firewall_context', 'custom_firewall_context'];

        yield ['the-username', null, ['ROLE_FOO'], null, 'main'];
        yield ['the-username', 'the-password', [], null, 'main'];
    }
}
