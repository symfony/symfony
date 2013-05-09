<?php

namespace Symfony\Component\Cache\Extension;

use Symfony\Component\Cache\Cache;
use Symfony\Component\Cache\Data\DataInterface;
use Symfony\Component\Cache\Data\KeyCollection;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface ExtensionInterface
{
    /**
     * Setup options resolver.
     *
     * @param OptionsResolverInterface $resolver
     *
     * @return ExtensionInterface
     */
    public function configure(OptionsResolverInterface $resolver);

    /**
     * @param array $query
     * @param array $options
     *
     * @return boolean
     */
    public function supportsQuery(array $query, array $options = array());

    /**
     * Fetches queried data.
     *
     * @param array $query
     * @param Cache $cache
     * @param array $options
     *
     * @return DataInterface
     */
    public function fetchResult(array $query, Cache $cache, array $options = array());

    /**
     * Builds fetched data before returning
     *
     * @param DataInterface $data
     * @param Cache         $cache
     * @param array         $options
     *
     * @return DataInterface
     */
    public function buildResult(DataInterface $data, Cache $cache, array $options = array());

    /**
     * Prepares data to be stored by driver.
     *
     * @param DataInterface $data
     * @param Cache         $cache
     * @param array         $options
     *
     * @return DataInterface
     */
    public function prepareStorage(DataInterface $data, Cache $cache, array $options = array());

    /**
     * Deletes queried data.
     *
     * @param array $query
     * @param Cache $cache
     * @param array $options
     *
     * @return KeyCollection
     */
    public function deleteData(array $query, Cache $cache, array $options = array());

    /**
     * Prepares data to be stored by driver.
     *
     * @param KeyCollection $keys
     * @param Cache         $cache
     * @param array         $options
     *
     * @return KeyCollection
     */
    public function propagateDeletion(KeyCollection $keys, Cache $cache, array $options = array());

    /**
     * Prepares data before flush.
     *
     * @param Cache $cache
     * @param array $options
     */
    public function prepareFlush(Cache $cache, array $options = array());
}
