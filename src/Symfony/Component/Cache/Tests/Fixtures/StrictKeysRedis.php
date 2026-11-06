<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Fixtures;

/**
 * Makes scripts fail when they access a key not passed in KEYS, like servers enforcing it (e.g. Dragonfly).
 */
class StrictKeysRedis extends \Redis
{
    private const PRELUDE = <<<'EOLUA'
        local __redis = redis
        local redis = setmetatable({
            call = function(command, key, ...)
                for _, k in ipairs(KEYS) do
                    if k == key then
                        return __redis.call(command, key, ...)
                    end
                end
                error('script tried accessing undeclared key: '..tostring(key))
            end,
        }, {__index = __redis})

        EOLUA;

    public function eval($script, $args = [], $num_keys = 0): mixed
    {
        return parent::eval(self::PRELUDE.$script, $args, $num_keys);
    }
}
