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

use Symfony\Component\Security\Http\EventListener\LoginThrottlingListener;

class FormLoginTest extends AbstractWebTestCase
{
    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLogin(array $options)
    {
        $client = $this->createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/profile".', $text);
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLogout(array $options)
    {
        $client = $this->createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');

        $crawler = $client->followRedirect();
        $text = $crawler->text(null, true);

        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/profile".', $text);

        $logoutLinks = $crawler->selectLink('Log out')->links();
        $this->assertCount(6, $logoutLinks);
        $this->assertSame($logoutLinks[0]->getUri(), $logoutLinks[1]->getUri());
        $this->assertSame($logoutLinks[2]->getUri(), $logoutLinks[3]->getUri());
        $this->assertSame($logoutLinks[4]->getUri(), $logoutLinks[5]->getUri());

        $this->assertNotSame($logoutLinks[0]->getUri(), $logoutLinks[2]->getUri());
        $this->assertNotSame($logoutLinks[1]->getUri(), $logoutLinks[3]->getUri());

        $this->assertSame($logoutLinks[0]->getUri(), $logoutLinks[4]->getUri());
        $this->assertSame($logoutLinks[1]->getUri(), $logoutLinks[5]->getUri());
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginWithCustomTargetPath(array $options)
    {
        $client = $this->createClient($options);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $form['_target_path'] = '/foo';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/foo');

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/foo".', $text);
    }

    /**
     * @dataProvider provideClientOptions
     */
    public function testFormLoginRedirectsToProtectedResourceAfterLogin(array $options)
    {
        $client = $this->createClient($options);

        $client->request('GET', '/protected_resource');
        $this->assertRedirect($client->getResponse(), '/login');

        $form = $client->followRedirect()->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);
        $this->assertRedirect($client->getResponse(), '/protected_resource');

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringContainsString('Hello johannes!', $text);
        $this->assertStringContainsString('You\'re browsing to path "/protected_resource".', $text);
    }

    /**
     * @dataProvider provideInvalidCredentials
     * @group time-sensitive
     */
    public function testLoginThrottling($username, $password)
    {
        if (!class_exists(LoginThrottlingListener::class)) {
            $this->markTestSkipped('Login throttling requires symfony/security-http:^5.2');
        }

        $client = $this->createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'login_throttling.yml', 'enable_authenticator_manager' => true]);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = $username;
        $form['_password'] = $password;
        $client->submit($form);

        $client->followRedirect()->selectButton('login')->form();
        $form['_username'] = $username;
        $form['_password'] = $password;
        $client->submit($form);

        $text = $client->followRedirect()->text(null, true);
        $this->assertStringMatchesFormat('%sToo many failed login attempts, please try again in %d minute%s', $text);
    }

    public function provideInvalidCredentials()
    {
        yield 'invalid_password' => ['johannes', 'wrong'];
        yield 'invalid_username' => ['wrong', 'wrong'];
    }

    public function provideClientOptions()
    {
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'config.yml', 'enable_authenticator_manager' => true]];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'legacy_config.yml', 'enable_authenticator_manager' => false]];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'routes_as_path.yml', 'enable_authenticator_manager' => true]];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'legacy_routes_as_path.yml', 'enable_authenticator_manager' => false]];
    }
}
