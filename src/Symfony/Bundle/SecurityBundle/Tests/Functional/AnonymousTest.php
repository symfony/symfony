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
class AnonymousTest extends AbstractWebTestCase
{
    public function testAnonymous()
    {
        $client = self::createClient(['test_case' => 'Anonymous', 'root_config' => 'config.yml']);

        $client->request('GET', '/');

        self::assertSame(401, $client->getResponse()->getStatusCode());
    }
}
