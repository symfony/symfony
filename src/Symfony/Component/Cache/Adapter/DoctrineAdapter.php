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
use Doctrine\Common\Cache\Psr6\CacheAdapter;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @deprecated Since Symfony 5.4, use Doctrine\Common\Cache\Psr6\CacheAdapter instead
 */
class DoctrineAdapter extends AbstractAdapter
{
    private $provider;

    public function __construct(CacheProvider $provider, string $namespace = '', int $defaultLifetime = 0)
    {
        trigger_deprecation('symfony/cache', '5.4', '"%s" is deprecated, use "%s" instead.', __CLASS__, CacheAdapter::class);

        parent::__construct('', $defaultLifetime);
        $this->provider = $provider;
        $provider->setNamespace($namespace);
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        parent::reset();
        $this->provider->setNamespace($this->provider->getNamespace());
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetch(array $ids)
    {
        $unserializeCallbackHandler = ini_set('unserialize_callback_func', parent::class.'::handleUnserializeCallback');
        try {
            return $this->provider->fetchMultiple($ids);
        } catch (\Error $e) {
            $trace = $e->getTrace();

            if (isset($trace[0]['function']) && !isset($trace[0]['class'])) {
                switch ($trace[0]['function']) {
                    case 'unserialize':
                    case 'apcu_fetch':
                    case 'apc_fetch':
                        throw new \ErrorException($e->getMessage(), $e->getCode(), \E_ERROR, $e->getFile(), $e->getLine());
                }
            }

            throw $e;
        } finally {
            ini_set('unserialize_callback_func', $unserializeCallbackHandler);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function doHave(string $id)
    {
        return $this->provider->contains($id);
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear(string $namespace)
    {
        $namespace = $this->provider->getNamespace();

        return isset($namespace[0])
            ? $this->provider->deleteAll()
            : $this->provider->flushAll();
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
    protected function doSave(array $values, int $lifetime)
    {
        return $this->provider->saveMultiple($values, $lifetime);
    }
}
