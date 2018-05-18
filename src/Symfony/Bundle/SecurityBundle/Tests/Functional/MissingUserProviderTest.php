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

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class MissingUserProviderTest extends WebTestCase
{
    public function testUserProviderIsNeeded()
    {
        $client = $this->createClient(array('test_case' => 'MissingUserProvider', 'root_config' => 'config.yml'));

        $client->request('GET', '/', array(), array(), array(
            'PHP_AUTH_USER' => 'username',
            'PHP_AUTH_PW' => 'pa$$word',
        ));

        $response = $client->getResponse();

        $this->assertSame(500, $response->getStatusCode());
        $this->assertContains(InvalidConfigurationException::class, $response->getContent());
        $this->assertContains('"default" firewall requires a user provider but none was defined', htmlspecialchars_decode($response->getContent()));
    }
}
