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

class SecurityRoutingIntegrationTest extends WebTestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testRoutingErrorIsNotExposedForProtectedResourceWhenAnonymous($config)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => $config));
        $client->insulate();
        $client->request('GET', '/protected_resource');

        $this->assertRedirect($client->getResponse(), '/login');
    }

    /**
     * @dataProvider getConfigs
     */
    public function testRoutingErrorIsExposedWhenNotProtected($config)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => $config));
        $client->insulate();
        $client->request('GET', '/unprotected_resource');

        $this->assertEquals(404, $client->getResponse()->getStatusCode(), (string) $client->getResponse());
    }

    /**
     * @dataProvider getConfigs
     */
    public function testRoutingErrorIsNotExposedForProtectedResourceWhenLoggedInWithInsufficientRights($config)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => $config));
        $client->insulate();

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $client->request('GET', '/highly_protected_resource');

        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
    }

    public function getConfigs()
    {
        return array(array('config.yml'), array('routes_as_path.yml'));
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
