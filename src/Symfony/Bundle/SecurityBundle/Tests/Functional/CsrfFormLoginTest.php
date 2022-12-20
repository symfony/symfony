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

class CsrfFormLoginTest extends AbstractWebTestCase
{
    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginAndLogoutWithCsrfTokens($options)
    {
        $client = self::createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $client->submit($form);

        self::assertRedirect($client->getResponse(), '/profile');

        $crawler = $client->followRedirect();

        $text = $crawler->text(null, true);
        self::assertStringContainsString('Hello johannes!', $text);
        self::assertStringContainsString('You\'re browsing to path "/profile".', $text);

        $logoutLinks = $crawler->selectLink('Log out')->links();
        self::assertCount(2, $logoutLinks);
        self::assertStringContainsString('_csrf_token=', $logoutLinks[0]->getUri());

        $client->click($logoutLinks[0]);

        self::assertRedirect($client->getResponse(), '/');
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginWithInvalidCsrfToken($options)
    {
        $client = self::createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[_token]'] = '';
        $client->submit($form);

        self::assertRedirect($client->getResponse(), '/login');

        $text = $client->followRedirect()->text(null, true);
        self::assertStringContainsString('Invalid CSRF token.', $text);
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginWithCustomTargetPath($options)
    {
        $client = self::createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $form['user_login[_target_path]'] = '/foo';
        $client->submit($form);

        self::assertRedirect($client->getResponse(), '/foo');

        $text = $client->followRedirect()->text(null, true);
        self::assertStringContainsString('Hello johannes!', $text);
        self::assertStringContainsString('You\'re browsing to path "/foo".', $text);
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginRedirectsToProtectedResourceAfterLogin($options)
    {
        $client = self::createClient($options);

        $client->request('GET', '/protected-resource');
        self::assertRedirect($client->getResponse(), '/login');

        $form = $client->followRedirect()->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $client->submit($form);
        self::assertRedirect($client->getResponse(), '/protected-resource');

        $text = $client->followRedirect()->text(null, true);
        self::assertStringContainsString('Hello johannes!', $text);
        self::assertStringContainsString('You\'re browsing to path "/protected-resource".', $text);
    }

    /**
     * @group legacy
     * @dataProvider provideLegacyClientOptions
     */
    public function testLegacyFormLoginAndLogoutWithCsrfTokens($options)
    {
        $client = self::createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $client->submit($form);

        self::assertRedirect($client->getResponse(), '/profile');

        $crawler = $client->followRedirect();

        $text = $crawler->text(null, true);
        self::assertStringContainsString('Hello johannes!', $text);
        self::assertStringContainsString('You\'re browsing to path "/profile".', $text);

        $logoutLinks = $crawler->selectLink('Log out')->links();
        self::assertCount(2, $logoutLinks);
        self::assertStringContainsString('_csrf_token=', $logoutLinks[0]->getUri());

        $client->click($logoutLinks[0]);

        self::assertRedirect($client->getResponse(), '/');
    }

    /**
     * @group legacy
     * @dataProvider provideLegacyClientOptions
     */
    public function testLegacyFormLoginWithInvalidCsrfToken($options)
    {
        $client = self::createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[_token]'] = '';
        $client->submit($form);

        self::assertRedirect($client->getResponse(), '/login');

        $text = $client->followRedirect()->text(null, true);
        self::assertStringContainsString('Invalid CSRF token.', $text);
    }

    /**
     * @group legacy
     * @dataProvider provideLegacyClientOptions
     */
    public function testFormLegacyLoginWithCustomTargetPath($options)
    {
        $client = self::createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $form['user_login[_target_path]'] = '/foo';
        $client->submit($form);

        self::assertRedirect($client->getResponse(), '/foo');

        $text = $client->followRedirect()->text(null, true);
        self::assertStringContainsString('Hello johannes!', $text);
        self::assertStringContainsString('You\'re browsing to path "/foo".', $text);
    }

    /**
     * @group legacy
     * @dataProvider provideLegacyClientOptions
     */
    public function testLegacyFormLoginRedirectsToProtectedResourceAfterLogin($options)
    {
        $client = self::createClient($options);

        $client->request('GET', '/protected-resource');
        self::assertRedirect($client->getResponse(), '/login');

        $form = $client->followRedirect()->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $client->submit($form);
        self::assertRedirect($client->getResponse(), '/protected-resource');

        $text = $client->followRedirect()->text(null, true);
        self::assertStringContainsString('Hello johannes!', $text);
        self::assertStringContainsString('You\'re browsing to path "/protected-resource".', $text);
    }

    public function provideClientOptions()
    {
        yield [['test_case' => 'CsrfFormLogin', 'root_config' => 'config.yml', 'enable_authenticator_manager' => true]];
        yield [['test_case' => 'CsrfFormLogin', 'root_config' => 'routes_as_path.yml', 'enable_authenticator_manager' => true]];
    }

    public function provideLegacyClientOptions()
    {
        yield [['test_case' => 'CsrfFormLogin', 'root_config' => 'legacy_config.yml', 'enable_authenticator_manager' => false]];
        yield [['test_case' => 'CsrfFormLogin', 'root_config' => 'legacy_routes_as_path.yml', 'enable_authenticator_manager' => false]];
    }
}
