<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\RedisExt;

use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory as BridgeRedisTransportFactory;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 5.1, use "%s" instead. The RedisExt transport has been moved to package "symfony/redis-messenger" and will not be included by default in 6.0. Run "composer require symfony/redis-messenger".', RedisTransportFactory::class, BridgeRedisTransportFactory::class), E_USER_DEPRECATED);

class_exists(BridgeRedisTransportFactory::class);

if (false) {
    /**
     * @deprecated since Symfony 5.1, to be removed in 6.0. Use symfony/redis-messenger instead.
     */
    class RedisTransportFactory
    {
    }
}
