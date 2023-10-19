<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Internal;

if (\PHP_VERSION_ID >= 80300) {
    /**
     * @internal
     */
    trait LazyObjectTrait
    {
        private readonly LazyObjectState $lazyObjectState;
    }
} else {
    /**
     * @internal
     */
    trait LazyObjectTrait
    {
        private LazyObjectState $lazyObjectState;
    }
}
