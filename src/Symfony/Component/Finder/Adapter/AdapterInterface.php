<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Adapter;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
interface AdapterInterface
{
    /**
     * @param Boolean $followLinks
     *
     * @return AdapterInterface Current instance
     */
    function setFollowLinks($followLinks);

    /**
     * @param integer $mode
     *
     * @return AdapterInterface Current instance
     */
    function setMode($mode);

    /**
     * @param array $exclude
     *
     * @return AdapterInterface Current instance
     */
    function setExclude(array $exclude);

    /**
     * @param array $depths
     *
     * @return AdapterInterface Current instance
     */
    function setDepths(array $depths);

    /**
     * @param array $names
     *
     * @return AdapterInterface Current instance
     */
    function setNames(array $names);

    /**
     * @param array $notNames
     *
     * @return AdapterInterface Current instance
     */
    function setNotNames(array $notNames);

    /**
     * @param array $contains
     *
     * @return AdapterInterface Current instance
     */
    function setContains(array $contains);

    /**
     * @param array $notContains
     *
     * @return AdapterInterface Current instance
     */
    function setNotContains(array $notContains);

    /**
     * @param array $sizes
     *
     * @return AdapterInterface Current instance
     */
    function setSizes(array $sizes);

    /**
     * @param array $dates
     *
     * @return AdapterInterface Current instance
     */
    function setDates(array $dates);

    /**
     * @param array $filters
     *
     * @return AdapterInterface Current instance
     */
    function setFilters(array $filters);

    /**
     * @param \Closure|integer $sort
     *
     * @return AdapterInterface Current instance
     */
    function setSort($sort);

    /**
     * @param array $paths
     *
     * @return AdapterInterface Current instance
     */
    function setPath(array $paths);

    /**
     * @param array $notPaths
     *
     * @return AdapterInterface Current instance
     */
    function setNotPath(array $notPaths);

    /**
     * @param string $dir
     *
     * @return \Iterator Result iterator
     */
    function searchInDirectory($dir);

    /**
     * Tests adapter support for current platform.
     *
     * @return Boolean
     */
    function isSupported();

    /**
     * Returns adapter name.
     *
     * @return string
     */
    function getName();
}
