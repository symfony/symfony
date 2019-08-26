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

class PredisSessionHandlerTest extends AbstractRedisSessionHandlerTestCase
{
    /**
     * @return Client
     */
    protected function createRedisClient(string $host): object
    {
        return new Client(['host' => $host]);
    }
}
