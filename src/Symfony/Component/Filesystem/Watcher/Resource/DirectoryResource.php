<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Watcher\Resource;

use Symfony\Component\Filesystem\Watcher\FileChangeEvent;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 *
 * @internal
 */
class DirectoryResource implements ResourceInterface
{
    private $dir;

    /**
     * @var FileResource[]
     */
    private $files;

    public function __construct(string $dir)
    {
        $this->dir = $dir;

        $this->files = $this->getFiles();
    }

    public function detectChanges(): array
    {
        $events = [];

        $currentFiles = $this->getFiles();

        // Check if any files has been added
        foreach (array_keys($currentFiles) as $path) {
            if (!isset($this->files[$path])) {
                $this->files = $currentFiles;

                $events[] = new FileChangeEvent($path, FileChangeEvent::FILE_CREATED);
            }
        }

        // Check if any files has been deleted
        foreach (array_keys($this->files) as $file) {
            if (!isset($currentFiles[$file])) {
                $this->files = $currentFiles;

                $events[] = new FileChangeEvent($file, FileChangeEvent::FILE_DELETED);
            }
        }

        // Check for any changes in files
        foreach ($this->files as $file) {
            if ($event = $file->detectChanges()) {
                $events = array_merge($events, $event);
            }
        }

        return $events;
    }

    private function getFiles(): array
    {
        $files = [];

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveDirectoryIterator($this->dir, \RecursiveDirectoryIterator::SKIP_DOTS) as $file) {
            $path = $file->getRealPath();
            $files[$path] = new FileResource($path);
        }

        return $files;
    }
}
