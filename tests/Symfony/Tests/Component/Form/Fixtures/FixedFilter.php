<?php

namespace Symfony\Tests\Component\Form\Fixtures;

use Symfony\Component\Form\Filters;
use Symfony\Component\Form\Filter\FilterInterface;

class FixedFilter implements FilterInterface
{
    private $mapping;

    public function __construct(array $mapping)
    {
        $this->mapping = array_merge(array(
            'filterBoundDataFromClient' => array(),
            'filterBoundData' => array(),
            'filterSetData' => array(),
        ), $mapping);
    }

    public function filterBoundDataFromClient($data)
    {
        if (isset($this->mapping['filterBoundDataFromClient'][$data])) {
            return $this->mapping['filterBoundDataFromClient'][$data];
        }

        return $data;
    }

    public function filterBoundData($data)
    {
        if (isset($this->mapping['filterBoundData'][$data])) {
            return $this->mapping['filterBoundData'][$data];
        }

        return $data;
    }

    public function filterSetData($data)
    {
        if (isset($this->mapping['filterSetData'][$data])) {
            return $this->mapping['filterSetData'][$data];
        }

        return $data;
    }

    public function getSupportedFilters()
    {
        return array(
            Filters::filterBoundDataFromClient,
            Filters::filterBoundData,
            Filters::filterSetData,
        );
    }
}