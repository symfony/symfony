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

if (version_compare(phpversion('relay'), '0.8.1', '>=')) {
    /**
     * @internal
     */
    trait RelayProxyTrait
    {
        public function copy($src, $dst, $options = null): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->copy(...\func_get_args());
        }

        public function jsonArrAppend($key, $value_or_array, $path = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonArrAppend(...\func_get_args());
        }

        public function jsonArrIndex($key, $path, $value, $start = 0, $stop = -1): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonArrIndex(...\func_get_args());
        }

        public function jsonArrInsert($key, $path, $index, $value, ...$other_values): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonArrInsert(...\func_get_args());
        }

        public function jsonArrLen($key, $path = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonArrLen(...\func_get_args());
        }

        public function jsonArrPop($key, $path = null, $index = -1): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonArrPop(...\func_get_args());
        }

        public function jsonArrTrim($key, $path, $start, $stop): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonArrTrim(...\func_get_args());
        }

        public function jsonClear($key, $path = null): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonClear(...\func_get_args());
        }

        public function jsonDebug($command, $key, $path = null): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonDebug(...\func_get_args());
        }

        public function jsonDel($key, $path = null): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonDel(...\func_get_args());
        }

        public function jsonForget($key, $path = null): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonForget(...\func_get_args());
        }

        public function jsonGet($key, $options = [], ...$paths): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonGet(...\func_get_args());
        }

        public function jsonMerge($key, $path, $value): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonMerge(...\func_get_args());
        }

        public function jsonMget($key_or_array, $path): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonMget(...\func_get_args());
        }

        public function jsonMset($key, $path, $value, ...$other_triples): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonMset(...\func_get_args());
        }

        public function jsonNumIncrBy($key, $path, $value): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonNumIncrBy(...\func_get_args());
        }

        public function jsonNumMultBy($key, $path, $value): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonNumMultBy(...\func_get_args());
        }

        public function jsonObjKeys($key, $path = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonObjKeys(...\func_get_args());
        }

        public function jsonObjLen($key, $path = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonObjLen(...\func_get_args());
        }

        public function jsonResp($key, $path = null): \Relay\Relay|array|false|int|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonResp(...\func_get_args());
        }

        public function jsonSet($key, $path, $value, $condition = null): \Relay\Relay|bool
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonSet(...\func_get_args());
        }

        public function jsonStrAppend($key, $value, $path = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonStrAppend(...\func_get_args());
        }

        public function jsonStrLen($key, $path = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonStrLen(...\func_get_args());
        }

        public function jsonToggle($key, $path): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonToggle(...\func_get_args());
        }

        public function jsonType($key, $path = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->jsonType(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait RelayProxyTrait
    {
        public function copy($src, $dst, $options = null): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->copy(...\func_get_args());
        }
    }
}
