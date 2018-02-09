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

class INotifyWatcher implements WatcherInterface
{
    public function watch($path, callable $callback)
    {
        $inotifyInit = inotify_init();
        $dir = false;

        stream_set_blocking($inotifyInit, 0);

        if (is_dir($path)) {
            $watchId = inotify_add_watch($inotifyInit, $path, IN_CREATE | IN_DELETE | IN_MODIFY);
            $dir = true;
        } else {
            $watchId = inotify_add_watch($inotifyInit, $path, IN_MODIFY);
        }

        while (true) {
            $events = inotify_read($inotifyInit);

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

                $callback(($dir ? $path : '').$event['name'], $code);
            }
        }

        inotify_rm_watch($inotifyInit, $watchId);
        fclose($inotifyInit);
    }
}
