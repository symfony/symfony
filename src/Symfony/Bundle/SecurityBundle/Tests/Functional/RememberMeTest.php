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

use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\RememberMeBundle\Security\UserChangingUserProvider;

class RememberMeTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        UserChangingUserProvider::$changePassword = false;
    }

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
        $this->assertNotNull($cookie = $cookieJar->get('REMEMBERME'));

        UserChangingUserProvider::$changePassword = true;

        // change password (through user provider), this deauthenticates the session
        $client->request('GET', '/profile');
        $this->assertRedirect($client->getResponse(), '/login');
        $this->assertNull($cookieJar->get('REMEMBERME'));

        // restore the old remember me cookie, it should no longer be valid
        $cookieJar->set($cookie);
        $client->request('GET', '/profile');
        $this->assertRedirect($client->getResponse(), '/login');
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

    public static function provideConfigs()
    {
        yield [['root_config' => 'config_session.yml']];
        yield [['root_config' => 'config_persistent.yml']];
    }
}
