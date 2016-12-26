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
     * @param bool $followLinks
     *
     * @return $this
     */
    public function setFollowLinks($followLinks);

    /**
     * @param int $mode
     *
     * @return $this
     */
    public function setMode($mode);

    /**
     * @param array $exclude
     *
     * @return $this
     */
    public function setExclude(array $exclude);

    /**
     * @param array $depths
     *
     * @return $this
     */
    public function setDepths(array $depths);

    /**
     * @param array $names
     *
     * @return $this
     */
    public function setNames(array $names);

    /**
     * @param array $notNames
     *
     * @return $this
     */
    public function setNotNames(array $notNames);

    /**
     * @param array $contains
     *
     * @return $this
     */
    public function setContains(array $contains);

    /**
     * @param array $notContains
     *
     * @return $this
     */
    public function setNotContains(array $notContains);

    /**
     * @param array $sizes
     *
     * @return $this
     */
    public function setSizes(array $sizes);

    /**
     * @param array $dates
     *
     * @return $this
     */
    public function setDates(array $dates);

    /**
     * @param array $filters
     *
     * @return $this
     */
    public function setFilters(array $filters);

    /**
     * @param \Closure|int $sort
     *
     * @return $this
     */
    public function setSort($sort);

    /**
     * @param array $paths
     *
     * @return $this
     */
    public function setPath(array $paths);

    /**
     * @param array $notPaths
     *
     * @return $this
     */
    public function setNotPath(array $notPaths);

    /**
     * @param bool $ignore
     *
     * @return $this
     */
    public function ignoreUnreadableDirs($ignore = true);

    /**
     * @param string $dir
     *
     * @return \Iterator Result iterator
     */
    public function searchInDirectory($dir);

    /**
     * Tests adapter support for current platform.
     *
     * @return bool
     */
    public function isSupported();

    /**
     * Returns adapter name.
     *
     * @return string
     */
    public function getName();
}
