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

class SwitchUserTest extends WebTestCase
{
    /**
     * @dataProvider getTestParameters
     */
    public function testSwitchUser($originalUser, $targetUser, $expectedUser, $expectedStatus)
    {
        $client = $this->createAuthenticatedClient($originalUser);

        $client->request('GET', '/profile?_switch_user='.$targetUser);

        $this->assertEquals($expectedStatus, $client->getResponse()->getStatusCode());
        $this->assertEquals($expectedUser, $client->getProfile()->getCollector('security')->getUser());
    }

    public function testSwitchedUserCannotSwitchToOther()
    {
        $client = $this->createAuthenticatedClient('user_can_switch');

        $client->request('GET', '/profile?_switch_user=user_cannot_switch_1');
        $client->request('GET', '/profile?_switch_user=user_cannot_switch_2');

        $this->assertEquals(500, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_cannot_switch_1', $client->getProfile()->getCollector('security')->getUser());
    }

    public function testSwitchedUserExit()
    {
        $client = $this->createAuthenticatedClient('user_can_switch');

        $client->request('GET', '/profile?_switch_user=user_cannot_switch_1');
        $client->request('GET', '/profile?_switch_user=_exit');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('user_can_switch', $client->getProfile()->getCollector('security')->getUser());
    }

    public function getTestParameters()
    {
        return array(
            'unauthorized_user_cannot_switch' => array('user_cannot_switch_1', 'user_cannot_switch_1', 'user_cannot_switch_1', 403),
            'authorized_user_can_switch' => array('user_can_switch', 'user_cannot_switch_1', 'user_cannot_switch_1', 200),
            'authorized_user_cannot_switch_to_non_existent' => array('user_can_switch', 'user_does_not_exist', 'user_can_switch', 500),
            'authorized_user_can_switch_to_himself' => array('user_can_switch', 'user_can_switch', 'user_can_switch', 200),
        );
    }

    protected function createAuthenticatedClient($username)
    {
        $client = $this->createClient(array('test_case' => 'StandardFormLogin', 'root_config' => 'switchuser.yml'));
        $client->followRedirects(true);

        $form = $client->request('GET', '/login')->selectButton('login')->form();
        $form['_username'] = $username;
        $form['_password'] = 'test';
        $client->submit($form);

        return $client;
    }

    public static function setUpBeforeClass()
    {
        parent::deleteTmpDir('StandardFormLogin');
    }

    public static function tearDownAfterClass()
    {
        parent::deleteTmpDir('StandardFormLogin');
    }
}
