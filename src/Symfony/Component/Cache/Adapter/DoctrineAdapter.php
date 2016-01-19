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

use Doctrine\Common\Cache\CacheProvider;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DoctrineAdapter extends AbstractAdapter
{
    private $provider;

    public function __construct(CacheProvider $provider, $defaultLifetime = null)
    {
        parent::__construct('', $defaultLifetime);
        $this->provider = $provider;
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        return $this->provider->fetchMultiple($ids);
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave($id)
    {
        return $this->provider->contains($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear()
    {
        return $this->provider->flushAll();
    }

    /**
     * {@inheritdoc}
     */
    protected function doDelete(array $ids)
    {
        $ok = true;
        foreach ($ids as $id) {
            $ok = $this->provider->delete($id) && $ok;
        }

        return $ok;
    }

    /**
     * {@inheritdoc}
     */
    protected function doSave(array $values, $lifetime)
    {
        return $this->provider->saveMultiple($values, $lifetime);
    }
}
