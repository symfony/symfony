<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

/**
 * @group functional
 */
class CsrfFormLoginTest extends WebTestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testFormLogin($config)
    {
        $client = $this->createClient(array('test_case' => 'CsrfFormLogin', 'root_config' => $config));
        $client->insulate();

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['user_login[username]'] = 'johannes';
        $form['user_login[password]'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');

        $text = $client->followRedirect()->text();
        $this->assertContains('Hello johannes!', $text);
        $this->assertContains('You\'re browsing to path "/profile".', $text);
    }

    /**
     * @dataProvider getConfigs
     */
    public function testFormLoginWithInvalidCsrfToken($config)
    {
        $client = $this->createClient(array('test_case' => 'CsrfFormLogin', 'root_config' => $config));
        $client->insulate();

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
        $client = $this->createClient(array('test_case' => 'CsrfFormLogin', 'root_config' => $config));
        $client->insulate();

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
        $client = $this->createClient(array('test_case' => 'CsrfFormLogin', 'root_config' => $config));
        $client->insulate();

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
        return array(
            array('config.yml'),
            array('routes_as_path.yml'),
        );
    }

    protected function setUp()
    {
        parent::setUp();

        $this->deleteTmpDir('CsrfFormLogin');
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->deleteTmpDir('CsrfFormLogin');
    }
}
