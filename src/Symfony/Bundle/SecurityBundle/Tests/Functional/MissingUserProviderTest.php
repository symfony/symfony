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

class MissingUserProviderTest extends AbstractWebTestCase
{
    public function testUserProviderIsNeeded()
    {
        $client = $this->createClient(['test_case' => 'MissingUserProvider', 'root_config' => 'config.yml']);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('"default" firewall requires a user provider but none was defined');

        $client->request('GET', '/', [], [], [
            'PHP_AUTH_USER' => 'username',
            'PHP_AUTH_PW' => 'pa$$word',
        ]);
    }
}
