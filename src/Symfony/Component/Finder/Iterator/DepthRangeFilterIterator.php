<?php

namespace Symfony\Component\Finder\Iterator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * DepthRangeFilterIterator limits the directory depth.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DepthRangeFilterIterator extends \FilterIterator
{
    protected $minDepth = 0;

    /**
     * Constructor.
     *
     * @param \RecursiveIteratorIterator $iterator    The Iterator to filter
     * @param array                      $comparators An array of \NumberComparator instances
     */
    public function __construct(\RecursiveIteratorIterator $iterator, array $comparators)
    {
        $minDepth = 0;
        $maxDepth = INF;
        foreach ($comparators as $comparator) {
            switch ($comparator->getOperator()) {
                case '>':
                    $minDepth = $comparator->getTarget() + 1;
                    break;
                case '>=':
                    $minDepth = $comparator->getTarget();
                    break;
                case '<':
                    $maxDepth = $comparator->getTarget() - 1;
                    break;
                case '<=':
                    $maxDepth = $comparator->getTarget();
                    break;
                default:
                    $minDepth = $maxDepth = $comparator->getTarget();
            }
        }

        $this->minDepth = $minDepth;
        $iterator->setMaxDepth(INF === $maxDepth ? -1 : $maxDepth);

        parent::__construct($iterator);
    }

    /**
     * Filters the iterator values.
     *
     * @return Boolean true if the value should be kept, false otherwise
     */
    public function accept()
    {
        return $this->getInnerIterator()->getDepth() >= $this->minDepth;
    }
}
