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
final class FileResource implements ResourceInterface
{
    private $file;

    private $lastModified;

    public function __construct(string $file)
    {
        $this->file = $file;
        $this->lastModified = filemtime($file);
    }

    public function detectChanges(): array
    {
        if ($this->isModified()) {
            $this->updateModifiedTime();

            return [new FileChangeEvent($this->file, FileChangeEvent::FILE_CHANGED)];
        }

        return [];
    }

    private function isModified(): bool
    {
        clearstatcache(false, $this->file);

        return $this->lastModified < filemtime($this->file);
    }

    private function updateModifiedTime(): void
    {
        $this->lastModified = filemtime($this->file);
    }
}
