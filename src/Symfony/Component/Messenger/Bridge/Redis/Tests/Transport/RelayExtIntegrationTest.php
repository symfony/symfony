<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Redis\Tests\Transport;

use Relay\Relay;

/**
 * @requires extension relay
 *
 * @group time-sensitive
 * @group integration
 */
class RelayExtIntegrationTest extends RedisExtIntegrationTest
{
    protected function createRedisClient(): \Redis|Relay
    {
        return new Relay();
    }
}
