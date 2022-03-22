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
use Symfony\Contracts\Service\ResetInterface;

if (!class_exists(CacheProvider::class)) {
    return;
}

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated Use Doctrine\Common\Cache\Psr6\DoctrineProvider instead
 */
class DoctrineProvider extends CacheProvider implements PruneableInterface, ResettableInterface
{
    private $pool;

    public function __construct(CacheItemPoolInterface $pool)
    {
        trigger_deprecation('symfony/cache', '5.4', '"%s" is deprecated, use "Doctrine\Common\Cache\Psr6\DoctrineProvider" instead.', __CLASS__);

        $this->pool = $pool;
    }

    /**
     * {@inheritdoc}
     */
    public function prune()
    {
        return $this->pool instanceof PruneableInterface && $this->pool->prune();
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        if ($this->pool instanceof ResetInterface) {
            $this->pool->reset();
        }
        $this->setNamespace($this->getNamespace());
    }

    /**
     * {@inheritdoc}
     *
     * @return mixed
     */
    protected function doFetch($id)
    {
        $item = $this->pool->getItem(rawurlencode($id));

        return $item->isHit() ? $item->get() : false;
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    protected function doContains($id)
    {
        return $this->pool->hasItem(rawurlencode($id));
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
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
     *
     * @return bool
     */
    protected function doDelete($id)
    {
        return $this->pool->deleteItem(rawurlencode($id));
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    protected function doFlush()
    {
        return $this->pool->clear();
    }

    /**
     * {@inheritdoc}
     *
     * @return array|null
     */
    protected function doGetStats()
    {
        return null;
    }
}
