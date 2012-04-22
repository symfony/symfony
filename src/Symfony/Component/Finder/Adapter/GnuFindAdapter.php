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

use Symfony\Component\Finder\Iterator;
use Symfony\Component\Finder\ShellTester;
use Symfony\Component\Finder\Expr;

/**
 * PHP finder engine implementation.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class GnuFindAdapter extends AbstractAdapter
{
    /**
     * {@inheritdoc}
     */
    public function searchInDirectory($dir)
    {
        // -noleaf option is required for filesystems
        // who doesn't follow '.' and '..' convention
        // like MSDOS, CDROM or AFS mount points
        $command = 'find '.$dir.' -noleaf';

        if ($this->followLinks) {
            $command.= ' -follow';
        }

        $command.= ' -mindepth '.($this->minDepth+1);

        // warning! INF < INF => true ; INF == INF => false ; INF === INF => true
        // https://bugs.php.net/bug.php?id=9118
        if ($this->maxDepth !== INF) {
            $command.= ' -maxdepth '.($this->maxDepth+1);
        }

        if (Iterator\FileTypeFilterIterator::ONLY_DIRECTORIES === $this->mode) {
            $command.= ' -type d';
        } elseif (Iterator\FileTypeFilterIterator::ONLY_FILES === $this->mode) {
            $command.= ' -type f';
        }

        $command.= $this->buildNamesOptions($this->names);
        $command.= $this->buildNamesOptions($this->notNames, true);
        $command.= $this->buildSizesOptions($this->sizes);

        exec($command, $paths, $code);

        if ($code !== 0) {
            throw new \RuntimeException();
        }

        $iterator = new Iterator\FilePathsIterator($paths, $dir);

        if ($this->exclude) {
            $iterator = new Iterator\ExcludeDirectoryFilterIterator($iterator, $this->exclude);
        }

        if ($this->contains || $this->notContains) {
            $iterator = new Iterator\FilecontentFilterIterator($iterator, $this->contains, $this->notContains);
        }

        if ($this->dates) {
            $iterator = new Iterator\DateRangeFilterIterator($iterator, $this->dates);
        }

        if ($this->filters) {
            $iterator = new Iterator\CustomFilterIterator($iterator, $this->filters);
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
    public function isValid()
    {
        $shell = new ShellTester();

        return $shell->getType() !== ShellTester::TYPE_WINDOWS
            && $shell->testCommand('find');
    }

    /**
     * @param string[] $names
     * @param bool     $not
     *
     * @return string
     */
    private function buildNamesOptions(array $names, $not = false)
    {
        if (0 === count($names)) {
            return '';
        }

        $options = array();

        foreach ($names as $name) {
            $expr = Expr::create($name);

            if ($expr->isRegex()) {
                $option = $expr->isCaseSensitive() ? '-regex' : '-iregex';
            } elseif ($expr->isGlob()) {
                $option = $expr->isCaseSensitive() ? '-name' : '-iname';
            } else {
                continue;
            }

            $options[] = $option.' '.escapeshellarg($expr->getBody());
        }

        return ' -regextype posix-extended'.($not ? ' -not ' : ' ').'\\( '.implode(' -or ', $options).' \\)';
    }

    /**
     * @param \Symfony\Component\Finder\Comparator\NumberComparator[] $sizes
     *
     * @return string
     */
    private function buildSizesOptions(array $sizes)
    {
        if (0 === count($sizes)) {
            return '';
        }

        $options = array();

        foreach ($sizes as $size) {
            if ('<=' === $size->getOperator()) {
                $options[] = '-size -'.($size->getTarget()+1).'c';
                continue;
            }

            if ('<' === $size->getOperator()) {
                $options[] = '-size -'.$size->getTarget().'c';
                continue;
            }

            if ('>=' === $size->getOperator()) {
                $options[] = '-size +'.($size->getTarget()-1).'c';
                continue;
            }

            if ('>' === $size->getOperator()) {
                $options[] = '-size +'.$size->getTarget().'c';
                continue;
            }

            if ('!=' === $size->getOperator()) {
                $options[] = '-size -'.$size->getTarget().'c';
                $options[] = '-size +'.$size->getTarget().'c';
                continue;
            }

            $options[] = '-size '.$size->getTarget().'c';
        }

        return ' \\( '.implode(' -and ', $options).' \\)';
    }
}
