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
    public function testRoutingErrorIsNotExposedForProtectedResourceWhenAnonymous()
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin'));
        $client->request('GET', '/protected_resource');

        $this->assertRedirect($client->getResponse(), '/login');
    }

    public function testRoutingErrorIsExposedWhenNotProtected()
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin'));
        $client->request('GET', '/unprotected_resource');

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testRoutingErrorIsNotExposedForProtectedResourceWhenLoggedInWithInsufficientRights()
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin'));

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $client->request('GET', '/highly_protected_resource');

        $this->assertNotEquals(404, $client->getResponse()->getStatusCode());
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
