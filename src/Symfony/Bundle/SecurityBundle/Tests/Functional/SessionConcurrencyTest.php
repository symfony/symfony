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
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 * @group functional
 */
class SessionConcurrencyTest extends WebTestCase
{
    public function testLoginWorksWhenConcurrentSessionsLesserThanMaximun()
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'session_concurrency.yml'));
        $client->insulate();
        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'johannes';
        $form['_password'] = 'test';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');
    }

    public function testLoginFailsWhenConcurrentSessionsGreaterOrEqualThanMaximun()
    {
        $client1 = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'session_concurrency.yml'));
        $client1->insulate();
        $form1 = $client1->request('GET', '/login')->selectButton('login')->form();
        $form1['_username'] = 'johannes';
        $form1['_password'] = 'test';
        $client1->submit($form1);

        $client2 = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'session_concurrency.yml'));
        $client2->insulate();
        $form2 = $client2->request('GET', '/login')->selectButton('login')->form();
        $form2['_username'] = 'johannes';
        $form2['_password'] = 'test';
        $client2->submit($form2);

        $this->assertRedirect($client2->getResponse(), '/login');
    }

    public function testOldSessionExpiresConcurrentSessionsGreaterOrEqualThanMaximun()
    {
        $client1 = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'session_concurrency_expiration.yml'));
        $client1->insulate();
        $form1 = $client1->request('GET', '/login')->selectButton('login')->form();
        $form1['_username'] = 'johannes';
        $form1['_password'] = 'test';
        $client1->submit($form1);
        $this->assertRedirect($client1->getResponse(), '/profile');

        $client2 = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'session_concurrency_expiration.yml'));
        $client2->insulate();
        $form2 = $client2->request('GET', '/login')->selectButton('login')->form();
        $form2['_username'] = 'johannes';
        $form2['_password'] = 'test';
        $client2->submit($form2);

        $this->assertRedirect($client2->getResponse(), '/profile');

        $client1->request('GET', '/profile');
        $this->assertRedirect($client1->getResponse(), '/expired');
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
