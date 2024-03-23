<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Cache;

use Twig\Cache\FilesystemCache;

/**
 * Implements a cache on the filesystem that can only be read, not written to.
 */
class ReadOnlyFilesystemCache extends FilesystemCache
{
    public function write(string $key, string $content): void
    {
        // Do nothing with the content, it's a read-only filesystem.
    }
}
