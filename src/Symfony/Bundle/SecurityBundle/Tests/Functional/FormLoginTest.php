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

use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FormLoginBundle\Security\User\FilesystemUserProvider;

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

    public function testFormLoginRedirectsToEntryPointWithAuthenticationExceptionMessage()
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'custom_provider.yml'));
        $client->insulate();

        // An user is added to the FilesystemUserProvider
        file_put_contents(FilesystemUserProvider::getFilename('StandardFormLogin'), json_encode(
            array('johannes' => array('password' => 'test', 'roles' => array('ROLE_USER')))
        ));

        $form = $client->request('GET', '/login')->selectButton('login')->form();

        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $client->followRedirect();

        // The username is modified between two requests
        file_put_contents(FilesystemUserProvider::getFilename('StandardFormLogin'), json_encode(
            array('johannes_' => array('password' => 'test', 'roles' => array('ROLE_USER')))
        ));

        $client->request('GET', '/profile');

        $crawler = $client->followRedirect();

        // The message from the authentication exception thrown during the refreshUser is displayed
        $this->assertContains('Username "johannes" does not exist.', $crawler->text());
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
