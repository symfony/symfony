<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Session\Storage\Handler;

use Relay\Relay;
use Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler\AbstractRedisSessionHandlerTestCase;

/**
 * @requires extension relay
 *
 * @group integration
 */
class RelaySessionHandlerTest extends AbstractRedisSessionHandlerTestCase
{
    protected function createRedisClient(string $host): Relay
    {
        return new Relay(...explode(':', $host));
    }
}
