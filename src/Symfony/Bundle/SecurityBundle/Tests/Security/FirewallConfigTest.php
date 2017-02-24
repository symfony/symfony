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
        $listeners = array('logout', 'remember_me', 'anonymous');
        $options = array(
            'request_matcher' => 'foo_request_matcher',
            'security' => false,
            'stateless' => false,
            'provider' => 'foo_provider',
            'context' => 'foo_context',
            'entry_point' => 'foo_entry_point',
            'access_denied_url' => 'foo_access_denied_url',
            'access_denied_handler' => 'foo_access_denied_handler',
            'user_checker' => 'foo_user_checker',
        );

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
            $listeners
        );

        $this->assertSame('foo_firewall', $config->getName());
        $this->assertSame($options['request_matcher'], $config->getRequestMatcher());
        $this->assertSame($options['security'], $config->isSecurityEnabled());
        $this->assertSame($options['stateless'], $config->isStateless());
        $this->assertSame($options['provider'], $config->getProvider());
        $this->assertSame($options['context'], $config->getContext());
        $this->assertSame($options['entry_point'], $config->getEntryPoint());
        $this->assertSame($options['access_denied_handler'], $config->getAccessDeniedHandler());
        $this->assertSame($options['access_denied_url'], $config->getAccessDeniedUrl());
        $this->assertSame($options['user_checker'], $config->getUserChecker());
        $this->assertTrue($config->allowsAnonymous());
        $this->assertSame($listeners, $config->getListeners());
    }
}
