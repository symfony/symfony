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

use Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\FirewallEntryPointBundle\Security\EntryPointStub;

class FirewallEntryPointTest extends AbstractWebTestCase
{
    public function testItUsesTheConfiguredEntryPointFromTheExceptionListenerWithFormLoginAndNoCredentials()
    {
        $client = self::createClient(['test_case' => 'FirewallEntryPoint', 'root_config' => 'config_form_login.yml']);

        $client->request('GET', '/secure/resource');

        self::assertEquals(EntryPointStub::RESPONSE_TEXT, $client->getResponse()->getContent(), "Custom entry point wasn't started");
    }

    /**
     * @group legacy
     */
    public function testItUsesTheConfiguredEntryPointWhenUsingUnknownCredentials()
    {
        $client = self::createClient(['test_case' => 'FirewallEntryPoint', 'root_config' => 'legacy_config.yml']);

        $client->request('GET', '/secure/resource', [], [], [
            'PHP_AUTH_USER' => 'unknown',
            'PHP_AUTH_PW' => 'credentials',
        ]);

        self::assertEquals(EntryPointStub::RESPONSE_TEXT, $client->getResponse()->getContent(), "Custom entry point wasn't started");
    }

    /**
     * @group legacy
     */
    public function testLegacyItUsesTheConfiguredEntryPointFromTheExceptionListenerWithFormLoginAndNoCredentials()
    {
        $client = self::createClient(['test_case' => 'FirewallEntryPoint', 'root_config' => 'legacy_config_form_login.yml']);

        $client->request('GET', '/secure/resource');

        self::assertEquals(EntryPointStub::RESPONSE_TEXT, $client->getResponse()->getContent(), "Custom entry point wasn't started");
    }
}
