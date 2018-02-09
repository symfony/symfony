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
class FileChangeEvent
{
    public const FILE_CHANGED = 1;
    public const FILE_DELETED = 2;
    public const FILE_CREATED = 3;

    private $file;

    private $event;

    public function __construct(string $file, int $event)
    {
        $this->file = $file;
        $this->event = $event;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getEvent(): int
    {
        return $this->event;
    }
}
