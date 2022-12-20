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

class AuthenticationCommencingTest extends AbstractWebTestCase
{
    public function testAuthenticationIsCommencingIfAccessDeniedExceptionIsWrapped()
    {
        $client = self::createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'base_config.yml']);

        $client->request('GET', '/secure-but-not-covered-by-access-control');
        self::assertRedirect($client->getResponse(), '/login');
    }

    /**
     * @group legacy
     */
    public function testLegacyAuthenticationIsCommencingIfAccessDeniedExceptionIsWrapped()
    {
        $client = self::createClient(['test_case' => 'StandardFormLogin', 'root_config' => 'legacy_config.yml']);

        $client->request('GET', '/secure-but-not-covered-by-access-control');
        self::assertRedirect($client->getResponse(), '/login');
    }
}
