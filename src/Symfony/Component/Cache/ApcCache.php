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

/**
 * APC Cache implementation
 *
 * Very simple implementation that can be used as default with various Symfony components
 * that support caching, such as Validation, ClassLoader.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class ApcCache implements CacheInterface
{
    public function __construct()
    {
        if (!extension_loaded('apc')) {
            throw new \RuntimeException("You need the APC php extension installed to use this cache driver.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function fetch($id)
    {
        return apc_fetch($id);
    }

    /**
     * {@inheritdoc}
     */
    public function contains($id)
    {
        $found = false;

        apc_fetch($id, $found);

        return $found;
    }

    /**
     * {@inheritdoc}
     */
    public function save($id, $data, $lifeTime = 0)
    {
        return (bool) apc_store($id, $data, (int) $lifeTime);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($id)
    {
        return apc_delete($id);
    }
}
