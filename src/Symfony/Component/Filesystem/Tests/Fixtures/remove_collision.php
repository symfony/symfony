<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem;

function random_bytes(int $length): string
{
    return isset($GLOBALS['filesystemRemoveCollision']) ? ($GLOBALS['filesystemRemoveCollision'])($length) : \random_bytes($length);
}

/**
 * @param \Traversable<array-key, mixed> $iterator
 *
 * @return array<array-key, mixed>
 */
function iterator_to_array(\Traversable $iterator, bool $preserveKeys = true): array
{
    return isset($GLOBALS['filesystemRemoveIterator']) ? ($GLOBALS['filesystemRemoveIterator'])($iterator, $preserveKeys) : \iterator_to_array($iterator, $preserveKeys);
}
