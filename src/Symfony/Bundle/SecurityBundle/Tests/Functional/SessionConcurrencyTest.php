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
        $client = $this->createClient(array('test_case' => 'SessionExpiration', 'root_config' => 'session_concurrency.yml'));
        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'antonio';
        $form['_password'] = 'secret';
        $client->submit($form);

        $this->assertRedirect($client->getResponse(), '/profile');
    }

    public function testLoginFailsWhenConcurrentSessionsGreaterOrEqualThanMaximun()
    {
        $client1 = $this->createClient(array('test_case' => 'SessionExpiration', 'root_config' => 'session_concurrency.yml'));
        $client1->insulate();
        $form1 = $client1->request('GET', '/login')->selectButton('login')->form();
        $form1['_username'] = 'antonio';
        $form1['_password'] = 'secret';
        $client1->submit($form1);

        $client2 = $this->createClient(array('test_case' => 'SessionExpiration', 'root_config' => 'session_concurrency.yml'));
        $client2->insulate();
        $form2 = $client2->request('GET', '/login')->selectButton('login')->form();
        $form2['_username'] = 'antonio';
        $form2['_password'] = 'secret';
        $client2->submit($form2);

        $this->assertRedirect($client2->getResponse(), '/login');
    }

    public function testOldSessionExpiresConcurrentSessionsGreaterOrEqualThanMaximun()
    {
        $client1 = $this->createClient(array('test_case' => 'SessionExpiration', 'root_config' => 'session_concurrency_expiration.yml'));
        $form1 = $client1->request('GET', '/login')->selectButton('login')->form();
        $form1['_username'] = 'antonio';
        $form1['_password'] = 'secret';
        $client1->submit($form1);
        $this->assertRedirect($client1->getResponse(), '/profile');

        $client2 = $this->createClient(array('test_case' => 'SessionExpiration', 'root_config' => 'session_concurrency_expiration.yml'));
        $client2->insulate();
        $form2 = $client2->request('GET', '/login')->selectButton('login')->form();
        $form2['_username'] = 'antonio';
        $form2['_password'] = 'secret';
        $client2->submit($form2);
        $this->assertRedirect($client2->getResponse(), '/profile');

        $client1->request('GET', '/profile');
        $this->assertEquals(200, $client1->getResponse()->getStatusCode());
        $sessionRegistry = $client1->getContainer()->get('security.session_registry');
        $session1Infomation = $sessionRegistry->getSessionInformation($client1->getRequest()->getSession()->getId());
        sleep(1); //Waiting for the session to expire
        $this->assertTrue($session1Infomation->isExpired());
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->deleteTmpDir('SessionExpiration');
    }
}
