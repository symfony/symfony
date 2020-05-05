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

use Symfony\Component\Filesystem\Exception\IOException;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 */
interface WatcherInterface
{
    /**
     * @param mixed    $path     The path to watch for changes. Can be a path to a file or directory, iterator or array with paths
     * @param callable $callback The callback to execute when a change is detected
     * @param float    $timeout  The idle timeout in milliseconds after which the process will be aborted if there are no changes detected
     */
    public function watch($path, callable $callback, float $timeout = null): void;
}
