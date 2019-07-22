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

class CsrfFormLoginTest extends WebTestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testFormLoginAndLogoutWithCsrfTokens($config)
    {
        $client = $this->createClient(['test_case' => 'CsrfFormLogin', 'root_config' => $config]);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');

        $crawler = $client->followRedirect();

        $text = $crawler->text();
        $this->assertContains('Hello johannes!', $text);
        $this->assertContains('You\'re browsing to path "/profile".', $text);

        $logoutLinks = $crawler->selectLink('Log out')->links();
        $this->assertCount(2, $logoutLinks);
        $this->assertContains('_csrf_token=', $logoutLinks[0]->getUri());
        $this->assertSame($logoutLinks[0]->getUri(), $logoutLinks[1]->getUri());

        $client->click($logoutLinks[0]);

        $this->assertRedirect($client->getResponse(), '/');
    }

    /**
     * @dataProvider getConfigs
     */
    public function testFormLoginWithInvalidCsrfToken($config)
    {
        $client = $this->createClient(['test_case' => 'CsrfFormLogin', 'root_config' => $config]);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[_token]'] = '';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/login');

        $text = $client->followRedirect()->text();
        $this->assertContains('Invalid CSRF token.', $text);
    }

    /**
     * @dataProvider getConfigs
     */
    public function testFormLoginWithCustomTargetPath($config)
    {
        $client = $this->createClient(['test_case' => 'CsrfFormLogin', 'root_config' => $config]);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $form['user_login[_target_path]'] = '/foo';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/foo');

        $text = $client->followRedirect()->text();
        $this->assertContains('Hello johannes!', $text);
        $this->assertContains('You\'re browsing to path "/foo".', $text);
    }

    /**
     * @dataProvider getConfigs
     */
    public function testFormLoginRedirectsToProtectedResourceAfterLogin($config)
    {
        $client = $this->createClient(['test_case' => 'CsrfFormLogin', 'root_config' => $config]);

        $client->request('GET', '/protected-resource');
        $this->assertRedirect($client->getResponse(), '/login');

        $form = $client->followRedirect()->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $client->submit($form);
        $this->assertRedirect($client->getResponse(), '/protected-resource');

        $text = $client->followRedirect()->text();
        $this->assertContains('Hello johannes!', $text);
        $this->assertContains('You\'re browsing to path "/protected-resource".', $text);
    }

    public function getConfigs()
    {
        return [
            ['config.yml'],
            ['routes_as_path.yml'],
        ];
    }
}
