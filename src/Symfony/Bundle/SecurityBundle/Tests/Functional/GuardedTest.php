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
 * @group legacy
 */
class GuardedTest extends AbstractWebTestCase
{
    public function testGuarded()
    {
        $client = self::createClient(['test_case' => 'Guarded', 'root_config' => 'config.yml']);

        $client->request('GET', '/');

        self::assertSame(418, $client->getResponse()->getStatusCode());
    }

    public function testManualLogin()
    {
        $client = self::createClient(['debug' => true, 'test_case' => 'Guarded', 'root_config' => 'config.yml']);

        $client->request('GET', '/manual_login');
        $client->request('GET', '/profile');

        self::assertSame('Username: Jane', $client->getResponse()->getContent());
    }
}
