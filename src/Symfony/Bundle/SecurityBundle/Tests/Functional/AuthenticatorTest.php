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
        $client = $this->createClient(['test_case' => 'Authenticator', 'root_config' => 'implicit_user_provider.yml']);

        $client->request('GET', '/profile', [], [], [
            'HTTP_X-USER-EMAIL' => $email,
        ]);
        $this->assertJsonStringEqualsJsonString('{"email":"'.$email.'"}', $client->getResponse()->getContent());
    }

    /**
     * @dataProvider provideEmails
     */
    public function testFirewallUserProvider($email, $withinFirewall)
    {
        $client = $this->createClient(['test_case' => 'Authenticator', 'root_config' => 'firewall_user_provider.yml']);

        $client->request('GET', '/profile', [], [], [
            'HTTP_X-USER-EMAIL' => $email,
        ]);

        if ($withinFirewall) {
            $this->assertJsonStringEqualsJsonString('{"email":"'.$email.'"}', $client->getResponse()->getContent());
        } else {
            $this->assertJsonStringEqualsJsonString('{"error":"Invalid credentials."}', $client->getResponse()->getContent());
        }
    }

    /**
     * @dataProvider provideEmails
     */
    public function testWithoutUserProvider($email)
    {
        $client = $this->createClient(['test_case' => 'Authenticator', 'root_config' => 'no_user_provider.yml']);

        $client->request('GET', '/profile', [], [], [
            'HTTP_X-USER-EMAIL' => $email,
        ]);

        $this->assertJsonStringEqualsJsonString('{"email":"'.$email.'"}', $client->getResponse()->getContent());
    }

    public static function provideEmails()
    {
        yield ['jane@example.org', true];
        yield ['john@example.org', false];
    }

    /**
     * @dataProvider provideEmailsWithFirewalls
     */
    public function testLoginUsersWithMultipleFirewalls(string $username, string $firewallContext)
    {
        $client = $this->createClient(['test_case' => 'Authenticator', 'root_config' => 'multiple_firewall_user_provider.yml']);
        $client->request('GET', '/main/login/check');

        $client->request('POST', '/'.$firewallContext.'/login/check', [
            '_username' => $username,
            '_password' => 'test',
        ]);
        $this->assertResponseRedirects('/'.$firewallContext.'/user_profile');

        $client->request('GET', '/'.$firewallContext.'/user_profile');
        $this->assertEquals('Welcome '.$username.'!', $client->getResponse()->getContent());
    }

    public static function provideEmailsWithFirewalls()
    {
        yield ['jane@example.org', 'main'];
        yield ['john@example.org', 'custom'];
    }

    public function testMultipleFirewalls()
    {
        $client = $this->createClient(['test_case' => 'Authenticator', 'root_config' => 'multiple_firewalls.yml']);

        $client->request('POST', '/firewall1/login', [
            '_username' => 'jane@example.org',
            '_password' => 'test',
        ]);

        $client->request('GET', '/firewall2/profile');
        $this->assertResponseRedirects('http://localhost/login');
    }

    public function testCustomSuccessHandler()
    {
        $client = $this->createClient(['test_case' => 'Authenticator', 'root_config' => 'custom_handlers.yml']);

        $client->request('POST', '/firewall1/login', [
            '_username' => 'jane@example.org',
            '_password' => 'test',
        ]);
        $this->assertResponseRedirects('http://localhost/firewall1/test');

        $client->request('POST', '/firewall1/dummy_login', [
            '_username' => 'jane@example.org',
            '_password' => 'test',
        ]);
        $this->assertResponseRedirects('http://localhost/firewall1/dummy');
    }

    public function testCustomFailureHandler()
    {
        $client = $this->createClient(['test_case' => 'Authenticator', 'root_config' => 'custom_handlers.yml']);

        $client->request('POST', '/firewall1/login', [
            '_username' => 'jane@example.org',
            '_password' => '',
        ]);
        $this->assertResponseRedirects('http://localhost/firewall1/login');

        $client->request('POST', '/firewall1/dummy_login', [
            '_username' => 'jane@example.org',
            '_password' => '',
        ]);
        $this->assertResponseRedirects('http://localhost/firewall1/dummy_login');
    }
}
