<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Filter;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FilterChain
{
    private $supportedTypes;

    private $filters = array();

    public function __construct(array $supportedTypes)
    {
        $this->supportedTypes = $supportedTypes;
    }

    public function prependFilter(FilterInterface $filter)
    {
        foreach ((array)$filter->getSupportedFilters() as $type) {
            // TODO check whether the filter has the $type method

            if (!isset($this->filters[$type])) {
                $this->filters[$type] = array();
            }

            array_unshift($this->filters[$type], $filter);
        }
    }

    public function appendFilter(FilterInterface $filter)
    {
        foreach ((array)$filter->getSupportedFilters() as $type) {
            // TODO check whether the filter has the $type method

            if (!isset($this->filters[$type])) {
                $this->filters[$type] = array();
            }

            $this->filters[$type][] = $filter;
        }
    }

    public function filter($type, $data)
    {
        if (isset($this->filters[$type])) {
            foreach ($this->filters[$type] as $filter) {
                $data = $filter->$type($data);
            }
        }

        return $data;
    }
}