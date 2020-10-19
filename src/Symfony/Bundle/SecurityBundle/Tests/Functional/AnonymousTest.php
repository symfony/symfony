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

class AnonymousTest extends AbstractWebTestCase
{
    public function testAnonymous()
    {
        $client = $this->createClient(['test_case' => 'Anonymous', 'root_config' => 'config.yml']);

        $client->request('GET', '/');

        $this->assertSame(401, $client->getResponse()->getStatusCode());
    }
}
