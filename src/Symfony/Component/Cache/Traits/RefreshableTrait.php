<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @internal
 */
trait RefreshableTrait
{
    private bool $refreshing = false;
    private array $refreshed = [];

    public function enableRefresh(bool $enable = true): void
    {
        $this->refreshing = $enable;
        $this->refreshed = [];
    }

    /**
     * Tag versions are bookkeeping rather than cached values: refreshing one would make the
     * next commit assign a new version to every tag and invalidate all the items carrying it.
     */
    private function isTagVersion(mixed $key): bool
    {
        return \is_string($key) && str_contains($key, TagAwareAdapter::TAGS_PREFIX);
    }

    /**
     * Whether reading $id should report a miss, marking it refreshed when it should.
     *
     * Call sites test $this->refreshing before calling this, so that reads pay a property
     * read rather than a method call while the mode is off.
     */
    private function shouldRefresh(string $id): bool
    {
        if (isset($this->refreshed[$id])) {
            return false;
        }

        if (1000 < \count($this->refreshed)) {
            $this->refreshed = \array_slice($this->refreshed, 500, null, true); // stop memory leak on long cycles
        }

        return $this->refreshed[$id] = true;
    }
}
