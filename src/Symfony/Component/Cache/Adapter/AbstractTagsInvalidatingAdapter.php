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

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractTagsInvalidatingAdapter extends AbstractAdapter implements TagsInvalidatingAdapterInterface
{
    /**
     * Persists several cache items immediately.
     *
     * @param array $values   The values to cache, indexed by their cache identifier.
     * @param int   $lifetime The lifetime of the cached values, 0 for persisting until manual cleaning.
     * @param array $tags     The tags corresponding to each value identifiers.
     *
     * @return array|bool The identifiers that failed to be cached or a boolean stating if caching succeeded or not.
     */
    abstract protected function doSaveWithTags(array $values, $lifetime, array $tags);

    /**
     * @internal
     */
    protected function doSave(array $values, $lifetime)
    {
        throw new \BadMethodCallException('Use doSaveWithTags() instead.');
    }
}
