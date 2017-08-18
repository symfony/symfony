<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Doctrine\Common\Cache\CacheProvider;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DoctrineProvider extends CacheProvider
{
    private $pool;

    public function __construct(CacheItemPoolInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch($id)
    {
        $item = $this->pool->getItem(rawurlencode($id));

        return $item->isHit() ? $item->get() : false;
    }

    /**
     * {@inheritdoc}
     */
    protected function doContains($id)
    {
        return $this->pool->hasItem(rawurlencode($id));
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave($id, $data, $lifeTime = 0)
    {
        $item = $this->pool->getItem(rawurlencode($id));

        if (0 < $lifeTime) {
            $item->expiresAfter($lifeTime);
        }

        return $this->pool->save($item->set($data));
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete($id)
    {
        return $this->pool->deleteItem(rawurlencode($id));
    }

    /**
     * {@inheritdoc}
     */
    protected function doFlush()
    {
        $this->pool->clear();
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetStats()
    {
    }
}
