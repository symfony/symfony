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
 * DateRangeFilterIterator filters out files that are not in the given date range (last modified dates).
 *
 * @package    Symfony
 * @subpackage Components_Finder
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class DateRangeFilterIterator extends \FilterIterator
{
    protected $minDate = false;
    protected $maxDate = false;

    /**
     * Constructor.
     *
     * @param \Iterator $iterator The Iterator to filter
     * @param integer   $minDate  The minimum date
     * @param integer   $maxDate  The maximum date
     */
    public function __construct(\Iterator $iterator, $minDate = false, $maxDate = false)
    {
        $this->minDate = $minDate;
        $this->maxDate = $maxDate;

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

        if (!$fileinfo->isFile()) {
            return true;
        }

        $filedate = $fileinfo->getMTime();

        if (
            (false !== $this->minDate && $filedate < $this->minDate)
            ||
            (false !== $this->maxDate && $filedate > $this->maxDate)
        ) {
            return false;
        }

        return true;
    }
}
