<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Watcher;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 */
interface WatcherInterface
{
    public function watch($path, callable $callback, float $timeout = null);
}
