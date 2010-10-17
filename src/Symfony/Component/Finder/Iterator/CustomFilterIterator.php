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
 * CustomFilterIterator filters files by applying anonymous functions.
 *
 * The anonymous function receives a \SplFileInfo and must return false
 * to remove files.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CustomFilterIterator extends \FilterIterator
{
    protected $filters = array();

    /**
     * Constructor.
     *
     * @param \Iterator $iterator The Iterator to filter
     * @param array     $filters  An array of \Closure
     */
    public function __construct(\Iterator $iterator, array $filters)
    {
        $this->filters = $filters;

        parent::__construct($iterator);
    }

    /**
     * Filters the iterator values.
     *
     * @return Boolean true if the value should be kept, false otherwise
     */
    public function accept()
    {
        $fileinfo = $this->getInnerIterator()->current();

        foreach ($this->filters as $filter) {
            if (false === $filter($fileinfo)) {
                return false;
            }
        }

        return true;
    }
}
