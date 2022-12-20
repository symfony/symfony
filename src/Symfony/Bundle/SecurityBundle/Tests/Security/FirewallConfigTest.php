<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;

class FirewallConfigTest extends TestCase
{
    public function testGetters()
    {
        $authenticators = ['form_login', 'remember_me'];
        $options = [
            'request_matcher' => 'foo_request_matcher',
            'security' => false,
            'stateless' => false,
            'provider' => 'foo_provider',
            'context' => 'foo_context',
            'entry_point' => 'foo_entry_point',
            'access_denied_url' => 'foo_access_denied_url',
            'access_denied_handler' => 'foo_access_denied_handler',
            'user_checker' => 'foo_user_checker',
            'switch_user' => ['provider' => null, 'parameter' => '_switch_user', 'role' => 'ROLE_ALLOWED_TO_SWITCH'],
        ];

        $config = new FirewallConfig(
            'foo_firewall',
            $options['user_checker'],
            $options['request_matcher'],
            $options['security'],
            $options['stateless'],
            $options['provider'],
            $options['context'],
            $options['entry_point'],
            $options['access_denied_handler'],
            $options['access_denied_url'],
            $authenticators,
            $options['switch_user']
        );

        self::assertSame('foo_firewall', $config->getName());
        self::assertSame($options['request_matcher'], $config->getRequestMatcher());
        self::assertSame($options['security'], $config->isSecurityEnabled());
        self::assertSame($options['stateless'], $config->isStateless());
        self::assertSame($options['provider'], $config->getProvider());
        self::assertSame($options['context'], $config->getContext());
        self::assertSame($options['entry_point'], $config->getEntryPoint());
        self::assertSame($options['access_denied_handler'], $config->getAccessDeniedHandler());
        self::assertSame($options['access_denied_url'], $config->getAccessDeniedUrl());
        self::assertSame($options['user_checker'], $config->getUserChecker());
        self::assertSame($authenticators, $config->getAuthenticators());
        self::assertSame($options['switch_user'], $config->getSwitchUser());
    }
}
