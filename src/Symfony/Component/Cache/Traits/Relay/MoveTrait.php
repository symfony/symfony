<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits\Relay;

if (version_compare(phpversion('relay'), '0.9.0', '>=')) {
    /**
     * @internal
     */
    trait MoveTrait
    {
        public function blmove($srckey, $dstkey, $srcpos, $dstpos, $timeout): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmove(...\func_get_args());
        }

        public function lmove($srckey, $dstkey, $srcpos, $dstpos): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmove(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait MoveTrait
    {
        public function blmove($srckey, $dstkey, $srcpos, $dstpos, $timeout): \Relay\Relay|false|null|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmove(...\func_get_args());
        }

        public function lmove($srckey, $dstkey, $srcpos, $dstpos): \Relay\Relay|false|null|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmove(...\func_get_args());
        }
    }
}
