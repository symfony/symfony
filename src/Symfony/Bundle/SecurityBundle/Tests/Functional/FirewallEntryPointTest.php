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

/**
 * @group functional
 */
class FirewallEntryPointTest extends WebTestCase
{
    public function testItUsesTheConfiguredEntryPointWhenUsingUnknownCredentials()
    {
        $client = $this->createClient(array('test_case' => 'FirewallEntryPoint'));
        $client->insulate();

        $client->request('GET', '/secure/resource', array(), array(), array(
            'PHP_AUTH_USER' => 'unknown',
            'PHP_AUTH_PW'   => 'credentials',
        ));

        $this->assertEquals(
            EntryPointStub::RESPONSE_TEXT,
            $client->getResponse()->getContent(),
            "Custom entry point wasn't started"
        );
    }

    protected function setUp()
    {
        parent::setUp();

        $this->deleteTmpDir('FirewallEntryPoint');
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->deleteTmpDir('FirewallEntryPoint');
    }
}
