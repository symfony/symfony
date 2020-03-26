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
 *
 * @internal
 */
final class INotifyWatcher implements WatcherInterface
{
    public function watch($path, callable $callback, float $timeout = null): void
    {
        $inotifyInit = inotify_init();

        if (false === $inotifyInit) {
            throw new IOException('Unable initialize inotify.', 0, null, $path);
        }

        stream_set_blocking($inotifyInit, false);

        $isDir = is_dir($path);
        $watchers = [];

        if ($isDir) {
            $watchers[] = inotify_add_watch($inotifyInit, $path, IN_CREATE | IN_DELETE | IN_MODIFY);

            foreach ($this->scanPath("$path/*") as $path) {
                $watchers[] = inotify_add_watch($inotifyInit, $path, IN_CREATE | IN_DELETE | IN_MODIFY);
            }
        } else {
            $watchers[] = inotify_add_watch($inotifyInit, $path, IN_MODIFY);
        }

        try {
            $read = [$inotifyInit];
            $write = null;
            $except = null;
            $tvSec = null === $timeout ? null : 0;
            $tvUsec = null === $timeout ? null : $timeout * 1000;

            while (true) {
                if (0 === stream_select($read, $write, $except, $tvSec, $tvUsec)) {
                    $read = [$inotifyInit];
                    break;
                }

                $events = inotify_read($inotifyInit);

                if (false === $events) {
                    continue;
                }

                foreach ($events as $event) {
                    $code = null;
                    switch ($event['mask']) {
                        case IN_CREATE:
                            $code = FileChangeEvent::FILE_CREATED;
                            break;
                        case IN_DELETE:
                            $code = FileChangeEvent::FILE_DELETED;
                            break;
                        case IN_MODIFY:
                            $code = FileChangeEvent::FILE_CHANGED;
                            break;
                    }

                    if (false === $callback(($isDir ? $path : '').$event['name'], $code)) {
                        break;
                    }
                }
            }
        } finally {
            foreach ($watchers as $watchId) {
                inotify_rm_watch($inotifyInit, $watchId);
            }

            fclose($inotifyInit);
        }
    }

    private function scanPath($path): iterable
    {
        foreach (glob($path, GLOB_ONLYDIR) as $directory) {
            yield $directory;
            yield from $this->scanPath("$directory/*");
        }
    }
}
