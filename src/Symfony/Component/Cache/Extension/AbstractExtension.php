<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractExtension implements ExtensionInterface
{
    /**
     * @var Cache|null
     */
    private $cache;

    /**
     * {@inheritdoc}
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(OptionsResolverInterface $resolver)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function supportsQuery(array $query, array $options)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveQuery(array $query, array $options)
    {
        return new KeyCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function buildResult(DataInterface $data, array $options)
    {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareStorage(DataInterface $data, array $options)
    {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveRemoval(array $query, array $options)
    {
        return new KeyCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function propagateRemoval(KeyCollection $keys, array $options)
    {
        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareClear(array $options)
    {
    }

    /**
     * @return Cache
     *
     * @throws \LogicException
     */
    protected function getCache()
    {
        if (null === $this->cache) {
            throw new \LogicException('Cache has not been set to extension.');
        }

        return $this->cache;
    }
}
