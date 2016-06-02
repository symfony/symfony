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
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class JsonLoginTest extends WebTestCase
{
    public function testJsonLoginSuccess()
    {
        $client = $this->createClient(array('test_case' => 'JsonLogin', 'root_config' => 'config.yml'));
        $client->request('POST', '/chk', array(), array(), array(), '{"user": {"login": "dunglas", "password": "foo"}}');
        $this->assertEquals('http://localhost/', $client->getResponse()->headers->get('location'));
    }

    public function testJsonLoginFailure()
    {
        $client = $this->createClient(array('test_case' => 'JsonLogin', 'root_config' => 'config.yml'));
        $client->request('POST', '/chk', array(), array(), array(), '{"user": {"login": "dunglas", "password": "bad"}}');
        $this->assertEquals('http://localhost/login', $client->getResponse()->headers->get('location'));
    }
}
