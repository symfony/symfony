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
    /**
     * @dataProvider provideClientOptions
     */
    public function testAuthenticationIsCommencingIfAccessDeniedExceptionIsWrapped(array $options)
    {
        $client = $this->createClient($options);

        $client->request('GET', '/secure-but-not-covered-by-access-control');
        $this->assertRedirect($client->getResponse(), '/login');
    }

    public function provideClientOptions()
    {
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'config.yml', 'enable_authenticator_manager' => true]];
        yield [['test_case' => 'StandardFormLogin', 'root_config' => 'legacy_config.yml', 'enable_authenticator_manager' => false]];
    }
}
