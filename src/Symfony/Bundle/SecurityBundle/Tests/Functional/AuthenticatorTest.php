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

class AuthenticatorTest extends AbstractWebTestCase
{
    /**
     * @group legacy
     *
     * @dataProvider provideEmails
     */
    public function testLegacyGlobalUserProvider($email)
    {
        $client = self::createClient(['test_case' => 'Authenticator', 'root_config' => 'implicit_user_provider.yml']);

        $client->request('GET', '/profile', [], [], [
            'HTTP_X-USER-EMAIL' => $email,
        ]);
        self::assertJsonStringEqualsJsonString('{"email":"'.$email.'"}', $client->getResponse()->getContent());
    }

    /**
     * @dataProvider provideEmails
     */
    public function testFirewallUserProvider($email, $withinFirewall)
    {
        $client = self::createClient(['test_case' => 'Authenticator', 'root_config' => 'firewall_user_provider.yml']);

        $client->request('GET', '/profile', [], [], [
            'HTTP_X-USER-EMAIL' => $email,
        ]);

        if ($withinFirewall) {
            self::assertJsonStringEqualsJsonString('{"email":"'.$email.'"}', $client->getResponse()->getContent());
        } else {
            self::assertJsonStringEqualsJsonString('{"error":"Invalid credentials."}', $client->getResponse()->getContent());
        }
    }

    /**
     * @dataProvider provideEmails
     */
    public function testWithoutUserProvider($email)
    {
        $client = self::createClient(['test_case' => 'Authenticator', 'root_config' => 'no_user_provider.yml']);

        $client->request('GET', '/profile', [], [], [
            'HTTP_X-USER-EMAIL' => $email,
        ]);

        self::assertJsonStringEqualsJsonString('{"email":"'.$email.'"}', $client->getResponse()->getContent());
    }

    public function provideEmails()
    {
        yield ['jane@example.org', true];
        yield ['john@example.org', false];
    }

    /**
     * @dataProvider provideEmailsWithFirewalls
     */
    public function testLoginUsersWithMultipleFirewalls(string $username, string $firewallContext)
    {
        $client = self::createClient(['test_case' => 'Authenticator', 'root_config' => 'multiple_firewall_user_provider.yml']);
        $client->request('GET', '/main/login/check');

        $client->request('POST', '/'.$firewallContext.'/login/check', [
            '_username' => $username,
            '_password' => 'test',
        ]);
        self::assertResponseRedirects('/'.$firewallContext.'/user_profile');

        $client->request('GET', '/'.$firewallContext.'/user_profile');
        self::assertEquals('Welcome '.$username.'!', $client->getResponse()->getContent());
    }

    public function provideEmailsWithFirewalls()
    {
        yield ['jane@example.org', 'main'];
        yield ['john@example.org', 'custom'];
    }

    public function testMultipleFirewalls()
    {
        $client = self::createClient(['test_case' => 'Authenticator', 'root_config' => 'multiple_firewalls.yml']);

        $client->request('POST', '/firewall1/login', [
            '_username' => 'jane@example.org',
            '_password' => 'test',
        ]);

        $client->request('GET', '/firewall2/profile');
        self::assertResponseRedirects('http://localhost/login');
    }
}
