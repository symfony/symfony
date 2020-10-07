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
     * @dataProvider provideEmails
     */
    public function testGlobalUserProvider($email)
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
            $this->assertJsonStringEqualsJsonString('{"error":"Username could not be found."}', $client->getResponse()->getContent());
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

    public function provideEmails()
    {
        yield ['jane@example.org', true];
        yield ['john@example.org', false];
    }
}
