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
    trait GetrangeTrait
    {
        public function getrange($key, $start, $end): mixed
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getrange(...\func_get_args());
        }
    }
} else {
    /**
     * @internal
     */
    trait GetrangeTrait
    {
        public function getrange($key, $start, $end): \Relay\Relay|false|string
        {
            return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->getrange(...\func_get_args());
        }
    }
}
