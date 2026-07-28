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

if (version_compare(phpversion('relay'), '0.40.0', '>=')) {
    /**
     * @internal
     */
    trait Relay40Trait
    {
        public function blmovem($srckey, $dstkey, $srcpos, $dstpos, $timeout, $options = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->blmovem(...\func_get_args());
        }

        public function lmovem($srckey, $dstkey, $srcpos, $dstpos, $options = null): \Relay\Relay|array|false
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->lmovem(...\func_get_args());
        }

        public function sdiffcard($keys, $options = null): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sdiffcard(...\func_get_args());
        }

        public function sunioncard($keys, $options = null): \Relay\Relay|false|int
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->sunioncard(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait Relay40Trait
    {
    }
}
