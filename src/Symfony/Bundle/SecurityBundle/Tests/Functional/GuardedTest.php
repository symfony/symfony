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

/**
 * @group legacy
 */
class GuardedTest extends AbstractWebTestCase
{
    public function testGuarded()
    {
        $client = $this->createClient(['test_case' => 'Guarded', 'root_config' => 'config.yml']);

        $client->request('GET', '/');

        $this->assertSame(418, $client->getResponse()->getStatusCode());
    }

    public function testManualLogin()
    {
        $client = $this->createClient(['debug' => true, 'test_case' => 'Guarded', 'root_config' => 'config.yml']);

        $client->request('GET', '/manual_login');
        $client->request('GET', '/profile');

        $this->assertSame('Username: Jane', $client->getResponse()->getContent());
    }

    public function testManualLoginWithAuthenticator()
    {
        $client = $this->createClient(['debug' => true, 'test_case' => 'Guarded', 'root_config' => 'config.yml']);

        $client->request('GET', '/manual_login_with_authenticator?username=Jane');
        $client->request('GET', '/profile');

        $this->assertSame('Username: Jane', $client->getResponse()->getContent());
    }

    public function testManualLoginWithAuthenticatorRunsThePostAuthCheckOfTheUserChecker()
    {
        $client = $this->createClient(['debug' => true, 'test_case' => 'Guarded', 'root_config' => 'config.yml']);

        $client->request('GET', '/manual_login_with_authenticator?username=Expired');
        $this->assertSame(401, $client->getResponse()->getStatusCode());

        $client->request('GET', '/profile');
        $this->assertSame('Not logged in.', $client->getResponse()->getContent());
    }
}
