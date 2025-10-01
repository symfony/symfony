<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class TraceableTagAwareAdapter extends TraceableAdapter implements TagAwareAdapterInterface, TagAwareCacheInterface
{
    public function __construct(TagAwareAdapterInterface $pool, ?\Closure $disabled = null)
    {
        parent::__construct($pool, $disabled);
    }

    public function invalidateTags(array $tags): bool
    {
        if ($this->disabled?->__invoke()) {
            return $this->pool->invalidateTags($tags);
        }
        $event = $this->start(__FUNCTION__);
        try {
            return $event->result = $this->pool->invalidateTags($tags);
        } finally {
            $event->end = microtime(true);
        }
    }
}
