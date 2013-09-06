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
 * @group functional
 */
class FormLoginTest extends WebTestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testFormLogin($config)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => $config));
        $client->insulate();

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');

        $text = $client->followRedirect()->text();
        $this->assertContains('Hello johannes!', $text);
        $this->assertContains('You\'re browsing to path "/profile".', $text);
    }

    /**
     * @dataProvider getConfigs
     */
    public function testFormLoginWithCustomTargetPath($config)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => $config));
        $client->insulate();

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $form['_target_path'] = '/foo';
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
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => $config));
        $client->insulate();

        $client->request('GET', '/protected_resource');
        $this->assertRedirect($client->getResponse(), '/login');

        $form = $client->followRedirect()->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);
        $this->assertRedirect($client->getResponse(), '/protected_resource');

        $text = $client->followRedirect()->text();
        $this->assertContains('Hello johannes!', $text);
        $this->assertContains('You\'re browsing to path "/protected_resource".', $text);
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

        $this->deleteTmpDir('StandardFormLogin');
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->deleteTmpDir('StandardFormLogin');
    }
}
