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

class RememberMeTest extends AbstractWebTestCase
{
    /**
     * @dataProvider provideConfigs
     */
    public function testRememberMe(array $options)
    {
        $client = $this->createClient(array_merge_recursive(['root_config' => 'config.yml', 'test_case' => 'RememberMe'], $options));
        $client->request('POST', '/login', [
            '_username' => 'johannes',
            '_password' => 'test',
        ]);
        $this->assertSame(302, $client->getResponse()->getStatusCode());

        $client->request('GET', '/profile');
        $this->assertSame('johannes', $client->getResponse()->getContent());

        // clear session, this should trigger remember me on the next request
        $client->getCookieJar()->expire('MOCKSESSID');

        $client->request('GET', '/profile');
        $this->assertSame('johannes', $client->getResponse()->getContent(), 'Not logged in after resetting session.');

        // logout, this should clear the remember-me cookie
        $client->request('GET', '/logout');
        $this->assertSame(302, $client->getResponse()->getStatusCode(), 'Logout unsuccessful.');
        $this->assertNull($client->getCookieJar()->get('REMEMBERME'));
    }

    public function testUserChangeClearsCookie()
    {
        $client = $this->createClient(['test_case' => 'RememberMe', 'root_config' => 'clear_on_change_config.yml']);

        $client->request('POST', '/login', [
            '_username' => 'johannes',
            '_password' => 'test',
        ]);

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $cookieJar = $client->getCookieJar();
        $this->assertNotNull($cookieJar->get('REMEMBERME'));

        $client->request('GET', '/profile');
        $this->assertRedirect($client->getResponse(), '/login');
        $this->assertNull($cookieJar->get('REMEMBERME'));
    }

    public function testSessionLessRememberMeLogout()
    {
        $client = $this->createClient(['test_case' => 'RememberMe', 'root_config' => 'stateless_config.yml']);

        $client->request('POST', '/login', [
            '_username' => 'johannes',
            '_password' => 'test',
        ]);

        $cookieJar = $client->getCookieJar();
        $cookieJar->expire(session_name());

        $this->assertNotNull($cookieJar->get('REMEMBERME'));
        $this->assertSame('lax', $cookieJar->get('REMEMBERME')->getSameSite());

        $client->request('GET', '/logout');
        $this->assertSame(302, $client->getResponse()->getStatusCode(), 'Logout unsuccessful.');
        $this->assertNull($cookieJar->get('REMEMBERME'));
    }

    /**
     * @dataProvider provideLegacyConfigs
     * @group legacy
     */
    public function testLegacyRememberMe(array $options)
    {
        $client = $this->createClient(array_merge_recursive(['root_config' => 'config.yml', 'test_case' => 'RememberMe'], $options));

        $client->request('POST', '/login', [
            '_username' => 'johannes',
            '_password' => 'test',
        ]);
        $this->assertSame(302, $client->getResponse()->getStatusCode());

        $client->request('GET', '/profile');
        $this->assertSame('johannes', $client->getResponse()->getContent());

        // clear session, this should trigger remember me on the next request
        $client->getCookieJar()->expire('MOCKSESSID');

        $client->request('GET', '/profile');
        $this->assertSame('johannes', $client->getResponse()->getContent(), 'Not logged in after resetting session.');

        // logout, this should clear the remember-me cookie
        $client->request('GET', '/logout');
        $this->assertSame(302, $client->getResponse()->getStatusCode(), 'Logout unsuccessful.');
        $this->assertNull($client->getCookieJar()->get('REMEMBERME'));
    }

    /**
     * @group legacy
     */
    public function testLegacyUserChangeClearsCookie()
    {
        $client = $this->createClient(['test_case' => 'RememberMe', 'root_config' => 'clear_on_change_config.yml']);

        $client->request('POST', '/login', [
            '_username' => 'johannes',
            '_password' => 'test',
        ]);

        $this->assertSame(302, $client->getResponse()->getStatusCode());
        $cookieJar = $client->getCookieJar();
        $this->assertNotNull($cookieJar->get('REMEMBERME'));

        $client->request('GET', '/profile');
        $this->assertRedirect($client->getResponse(), '/login');
        $this->assertNull($cookieJar->get('REMEMBERME'));
    }

    /**
     * @group legacy
     */
    public function testLegacySessionLessRememberMeLogout()
    {
        $client = $this->createClient(['test_case' => 'RememberMe', 'root_config' => 'stateless_config.yml']);

        $client->request('POST', '/login', [
            '_username' => 'johannes',
            '_password' => 'test',
        ]);

        $cookieJar = $client->getCookieJar();
        $cookieJar->expire(session_name());

        $this->assertNotNull($cookieJar->get('REMEMBERME'));
        $this->assertSame('lax', $cookieJar->get('REMEMBERME')->getSameSite());

        $client->request('GET', '/logout');
        $this->assertSame(302, $client->getResponse()->getStatusCode(), 'Logout unsuccessful.');
        $this->assertNull($cookieJar->get('REMEMBERME'));
    }

    public function provideConfigs()
    {
        yield [['root_config' => 'config_session.yml']];
        yield [['root_config' => 'config_persistent.yml']];
    }

    public function provideLegacyConfigs()
    {
        yield [['root_config' => 'legacy_config_session.yml']];
        yield [['root_config' => 'legacy_config_persistent.yml']];
    }
}
