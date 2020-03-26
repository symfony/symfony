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

use Symfony\Component\Filesystem\Watcher\Resource\Locator\FileResourceLocator;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 *
 * @internal
 */
final class FileChangeWatcher implements WatcherInterface
{
    private $locator;

    public function __construct()
    {
        $this->locator = new FileResourceLocator();
    }

    public function watch($path, callable $callback, float $timeout = null): void
    {
        $resource = $this->locator->locate($path);

        if (!$resource) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid path to watch.', \gettype($path)));
        }

        $run = true;
        $start = microtime(true);

        while ($run) {
            if ($changes = $resource->detectChanges()) {
                foreach ($changes as $change) {
                    $run = false !== $callback($change->getFile(), $change->getEvent());
                }

                $start = microtime(true);
            }

            if (null !== $timeout && ($timeout / 1000) <= (microtime(true) - $start)) {
                break;
            }

            sleep(1);
        }
    }
}
