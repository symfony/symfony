<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
     * Gives a cache to the extension.
     *
     * @param Cache $cache
     *
     * @return ExtensionInterface
     */
    public function setCache(Cache $cache);

    /**
     * Setup options resolver.
     *
     * @param OptionsResolverInterface $resolver
     *
     * @return ExtensionInterface
     */
    public function configure(OptionsResolverInterface $resolver);

    /**
     * Tests if given query is supported by extension.
     *
     * @param array $query
     * @param array $options
     *
     * @return boolean
     */
    public function supportsQuery(array $query, array $options);

    /**
     * Resolves query and return keys to fetch.
     *
     * @param array $query
     * @param array $options
     *
     * @return KeyCollection
     */
    public function resolveQuery(array $query, array $options);

    /**
     * Builds fetched data before returning
     *
     * @param DataInterface $data
     * @param array         $options
     *
     * @return DataInterface
     */
    public function buildResult(DataInterface $data, array $options);

    /**
     * Prepares data to be stored by driver.
     *
     * @param DataInterface $data
     * @param array         $options
     *
     * @return DataInterface
     */
    public function prepareStorage(DataInterface $data, array $options);

    /**
     * Resolves query and return keys to delete.
     *
     * @param array $query
     * @param array $options
     *
     * @return KeyCollection
     */
    public function resolveRemoval(array $query, array $options);

    /**
     * Prepares data to be removed by driver.
     *
     * This method is used to add or remove keys to remove.
     *
     * @param KeyCollection $keys
     * @param array         $options
     *
     * @return KeyCollection
     */
    public function prepareRemoval(KeyCollection $keys, array $options);

    /**
     * Prepares data before flush.
     *
     * @param array $options
     */
    public function prepareClear(array $options);

    /**
     * Returns extension name.
     *
     * @return string
     */
    public function getName();

    /**
     * Returns required extension names.
     *
     * @return string
     */
    public function getRequiredExtensions();
}
