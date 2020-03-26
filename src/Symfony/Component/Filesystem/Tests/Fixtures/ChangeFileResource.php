<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem\Tests\Fixtures;

use Symfony\Component\Filesystem\Watcher\FileChangeEvent;
use Symfony\Component\Filesystem\Watcher\Resource\ResourceInterface;

/**
 * @author Pierre du Plessis <pdples@gmail.com>
 */
final class ChangeFileResource implements ResourceInterface
{
    private $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function detectChanges(): array
    {
        return [new FileChangeEvent($this->path, FileChangeEvent::FILE_CHANGED)];
    }
}
