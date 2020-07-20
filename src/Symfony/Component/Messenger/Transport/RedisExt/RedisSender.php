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

use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisSender as BridgeRedisSender;

trigger_deprecation('symfony/messenger', '5.1', 'The "%s" class is deprecated, use "%s" instead. The RedisExt transport has been moved to package "symfony/redis-messenger" and will not be included by default in 6.0. Run "composer require symfony/redis-messenger".', RedisSender::class, BridgeRedisSender::class);

class_exists(BridgeRedisSender::class);

if (false) {
    /**
     * @deprecated since Symfony 5.1, to be removed in 6.0. Use symfony/redis-messenger instead.
     */
    class RedisSender
    {
    }
}
