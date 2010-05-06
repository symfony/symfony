<?php

namespace Symfony\Components\Finder\Iterator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * LimitDepthFilterIterator limits the directory depth.
 *
 * @package    Symfony
 * @subpackage Components_Finder
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LimitDepthFilterIterator extends \FilterIterator
{
    protected $minDepth = 0;

    /**
     * Constructor.
     *
     * @param \Iterator $iterator The Iterator to filter
     * @param integer   $minDepth The minimum depth
     */
    public function __construct(\RecursiveIteratorIterator $iterator, $minDepth, $maxDepth)
    {
        $this->minDepth = (integer) $minDepth;

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
