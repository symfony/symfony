<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\Cache\Data\NullResult;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractExtension implements ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function configure(OptionsResolverInterface $resolver)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function supportsQuery(array $query, array $options = array())
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchResult(array $query, Cache $cache, array $options = array())
    {
        return new NullResult();
    }

    /**
     * {@inheritdoc}
     */
    public function buildResult(DataInterface $data, Cache $cache, array $options = array())
    {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareStorage(DataInterface $data, Cache $cache, array $options = array())
    {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteData(array $query, Cache $cache, array $options = array())
    {
        return new NullResult();
    }

    /**
     * {@inheritdoc}
     */
    public function propagateDeletion(KeyCollection $keys, Cache $cache, array $options = array())
    {
        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareFlush(Cache $cache, array $options = array())
    {
    }
}
