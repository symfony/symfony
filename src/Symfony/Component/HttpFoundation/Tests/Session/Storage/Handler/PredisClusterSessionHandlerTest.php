<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use Predis\Client;

/**
 * @group integration
 */
class PredisClusterSessionHandlerTest extends AbstractRedisSessionHandlerTestCase
{
    protected function createRedisClient(string $host): Client
    {
        return new Client(
            [array_combine(['host', 'port'], explode(':', getenv('REDIS_HOST')) + [1 => 6379])],
            ['cluster' => 'redis']
        );
    }
}
