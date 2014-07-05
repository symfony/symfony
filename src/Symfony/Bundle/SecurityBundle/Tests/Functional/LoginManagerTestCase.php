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
class LoginManagerTestCase extends WebTestCase
{
    public function testLoginUserInController()
    {
        $client = $this->createClient(array('test_case' => 'LoginManager'));
        $client->insulate();
        $client->request('GET', '/login');
        $client->request('GET', '/secured/index');
        $this->assertEquals('Secured area', $client->getResponse()->getContent());
    }
}
