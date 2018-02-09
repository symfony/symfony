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
use Symfony\Component\Filesystem\Watcher\Resource\Locator\LocatorInterface;

class FileChangeWatcher implements WatcherInterface
{
    private $wait;

    private $timeout;

    private $locator;

    public function __construct(LocatorInterface $locator = null, int $timeout = -1, int $wait = 1)
    {
        $this->locator = $locator ?: new FileResourceLocator();
        $this->timeout = $timeout;
        $this->wait = $wait;
    }

    public function watch($path, callable $callback)
    {
        $resource = $this->locator->locate($path);

        if (!$resource) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid path to watch', gettype($path)));
        }

        $time = 0;

        while (true) {
            if ($changes = $resource->detectChanges()) {
                foreach ($changes as $change) {
                    $callback($change->getFile(), $change->getEvent());
                }
            }

            sleep($this->wait);

            $time += $this->wait;

            if ($this->timeout > -1 && $time >= $this->timeout) {
                break;
            }
        }
    }
}
