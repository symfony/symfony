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
        $client = self::createClient(['enable_authenticator_manager' => true, 'test_case' => 'MissingUserProvider', 'root_config' => 'config.yml', 'debug' => true]);

        self::expectException(InvalidConfigurationException::class);
        self::expectExceptionMessage('"default" firewall requires a user provider but none was defined');

        $client->request('GET', '/', [], [], [
            'PHP_AUTH_USER' => 'username',
            'PHP_AUTH_PW' => 'pa$$word',
        ]);
    }

    /**
     * @group legacy
     */
    public function testLegacyUserProviderIsNeeded()
    {
        $client = self::createClient(['test_case' => 'MissingUserProvider', 'root_config' => 'config.yml', 'debug' => true]);

        $client->request('GET', '/', [], [], [
            'PHP_AUTH_USER' => 'username',
            'PHP_AUTH_PW' => 'pa$$word',
        ]);

        $response = $client->getResponse();
        self::assertSame(500, $response->getStatusCode());
        self::assertStringContainsString('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException', $response->getContent());
        self::assertStringContainsString('"default" firewall requires a user provider but none was defined', html_entity_decode($response->getContent()));
    }
}
