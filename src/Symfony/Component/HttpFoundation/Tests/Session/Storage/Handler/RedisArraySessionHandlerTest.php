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

class RedisArraySessionHandlerTest extends AbstractRedisSessionHandlerTestCase
{
    /**
     * @return \RedisArray|object
     */
    protected function createRedisClient(string $host)
    {
        return new \RedisArray([$host]);
    }
}
