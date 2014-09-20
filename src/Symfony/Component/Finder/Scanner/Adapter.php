<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Scanner;

use Symfony\Component\Finder\Adapter\AbstractAdapter;
use Symfony\Component\Finder\Iterator;

/**
 * PHP finder engine implementation.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Adapter extends AbstractAdapter
{
    /**
     * {@inheritdoc}
     */
    public function searchInDirectory($dir)
    {
        $builder = new Builder($this->mode, $this->minDepth, $this->maxDepth, $this->exclude);

        foreach ($this->notPaths as $value) {
            $builder->notPath(new Expression($value));
        }

        foreach ($this->notNames as $value) {
            $builder->notName(new Expression($value));
        }

        foreach ($this->paths as $value) {
            $builder->path(new Expression($value));
        }

        foreach ($this->names as $value) {
            $builder->name(new Expression($value));
        }

        $scanner = new Scanner($dir, $builder->build(), $this->ignoreUnreadableDirs, $this->followLinks);
        $iterator = $scanner->getIterator();

        if ($this->sizes) {
            $iterator = new Iterator\SizeRangeFilterIterator($iterator, $this->sizes);
        }

        if ($this->dates) {
            $iterator = new Iterator\DateRangeFilterIterator($iterator, $this->dates);
        }

        if ($this->filters) {
            $iterator = new Iterator\CustomFilterIterator($iterator, $this->filters);
        }

        if ($this->contains || $this->notContains) {
            $iterator = new Iterator\FilecontentFilterIterator($iterator, $this->contains, $this->notContains);
        }

        if ($this->sort) {
            $iteratorAggregate = new Iterator\SortableIterator($iterator, $this->sort);
            $iterator = $iteratorAggregate->getIterator();
        }

        return $iterator;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'scanner';
    }

    /**
     * {@inheritdoc}
     */
    protected function canBeUsed()
    {
        return true;
    }
}
