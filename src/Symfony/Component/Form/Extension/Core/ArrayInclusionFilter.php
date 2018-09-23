<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core;

/**
 * @author Sebastiaan Stok <s.stok@rollerscapes.net>
 *
 * @internal
 */
final class ArrayInclusionFilter
{
    private $filterCallable;
    private $acceptedKeys;

    public function __construct($filter)
    {
        if (\is_array($filter)) {
            $this->acceptedKeys = array_fill_keys($filter, true);
            $this->filterCallable = $this;
        } else {
            $this->acceptedKeys = [];
            $this->filterCallable = $filter;
        }
    }

    public function filter(array $choices)
    {
        return array_filter($choices, $this->filterCallable, ARRAY_FILTER_USE_BOTH);
    }

    public function __invoke($v, $k)
    {
        return isset($this->acceptedKeys[$k]);
    }
}
