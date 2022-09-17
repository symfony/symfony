<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

class_alias(6.0 <= (float) phpversion('redis') ? Redis6Proxy::class : Redis5Proxy::class, RedisProxy::class);

if (false) {
    /**
     * @internal
     */
    class RedisProxy extends \Redis
    {
    }
}
