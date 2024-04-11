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

class LazyFirewallTokenStorageUsageTrackingTest extends AbstractWebTestCase
{
    public function testTokenStorageUsageIsTracked()
    {
        $client = $this->createClient(['test_case' => 'LazyFirewallTokenStorageUsageTracking']);
        $client->request('GET', '/');

        $this->assertSame(1, $client->getRequest()->getSession()->getUsageIndex());
    }
}
