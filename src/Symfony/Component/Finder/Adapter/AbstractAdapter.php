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
 * Interface for finder engine implementations.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractAdapter implements AdapterInterface
{
    protected $followLinks = false;
    protected $mode        = 0;
    protected $depths      = array();
    protected $exclude     = array();
    protected $names       = array();
    protected $notNames    = array();
    protected $contains    = array();
    protected $notContains = array();
    protected $sizes       = array();
    protected $dates       = array();
    protected $filters     = array();
    protected $sort        = array();

    /**
     * {@inheritdoc}
     */
    public function setFollowLinks($followLinks)
    {
        $this->followLinks = $followLinks;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setDepths(array $depths)
    {
        $this->depths = $depths;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setExclude(array $exclude)
    {
        $this->exclude = $exclude;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setNames(array $names)
    {
        $this->names = $names;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setNotNames(array $notNames)
    {
        $this->notNames = $notNames;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setContains(array $contains)
    {
        $this->contains = $contains;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setNotContains(array $notContains)
    {
        $this->notContains = $notContains;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setSizes(array $sizes)
    {
        $this->sizes = $sizes;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setDates(array $dates)
    {
        $this->dates = $dates;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setFilters(array $filters)
    {
        $this->filters = $filters;

        return $this;
     }

    /**
     * {@inheritdoc}
     */
    public function setSort($sort)
    {
        $this->sort = $sort;

        return $this;
     }
}
