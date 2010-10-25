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
 * FileTypeFilterIterator only keeps files, directories, or both.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FileTypeFilterIterator extends \FilterIterator
{
    const ONLY_FILES       = 1;
    const ONLY_DIRECTORIES = 2;

    protected $mode;

    /**
     * Constructor.
     *
     * @param \Iterator $iterator The Iterator to filter
     * @param integer   $mode     The mode (self::ONLY_FILES or self::ONLY_DIRECTORIES)
     */
    public function __construct(\Iterator $iterator, $mode)
    {
        $this->mode = $mode;

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

        if (self::ONLY_DIRECTORIES === (self::ONLY_DIRECTORIES & $this->mode) && $fileinfo->isFile()) {
            return false;
        } elseif (self::ONLY_FILES === (self::ONLY_FILES & $this->mode) && $fileinfo->isDir()) {
            return false;
        }

        return true;
    }
}
