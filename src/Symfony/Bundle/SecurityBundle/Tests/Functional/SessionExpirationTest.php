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

use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Security\Http\Firewall\SessionExpirationListener;

/**
 * @group functional
 * @group time-sensitive
 */
class SessionExpirationTest extends WebTestCase
{
    protected function setUp()
    {
        parent::setUp();

        ClockMock::register(SessionExpirationListener::class);
    }

    public function testExpiredExceptionRedirectsToTargetUrl()
    {
        $client = $this->createClient(array('test_case' => 'SessionExpiration', 'root_config' => 'config.yml'));
        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = 'antonio';
        $form['_password'] = 'secret';
        $client->submit($form);
        $this->assertRedirect($client->getResponse(), '/profile');

        $client->request('GET', '/protected_resource');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        sleep(5); //Wait for session to expire
        $client->request('GET', '/protected_resource');
        $this->assertRedirect($client->getResponse(), '/expired');
    }

    protected function tearDown()
    {
        $this->deleteTmpDir('SessionExpiration');

        parent::tearDown();
    }
}
