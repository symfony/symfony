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
use Symfony\Component\Finder\Shell;
use Symfony\Component\Finder\Expr;
use Symfony\Component\Finder\Command;

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
        $command = Command::create('find ')->arg($dir)->add('-noleaf');

        if ($this->followLinks) {
            $command->add('-follow');
        }

        $command->add('-mindepth')->add($this->minDepth+1);

        // warning! INF < INF => true ; INF == INF => false ; INF === INF => true
        // https://bugs.php.net/bug.php?id=9118
        if (INF !== $this->maxDepth) {
            $command->add('-maxdepth')->add($this->maxDepth+1);
        }

        if (Iterator\FileTypeFilterIterator::ONLY_DIRECTORIES === $this->mode) {
            $command->add('-type d');
        } elseif (Iterator\FileTypeFilterIterator::ONLY_FILES === $this->mode) {
            $command->add('-type f');
        }

        $this->buildNamesOptions($command, $this->names);
        $this->buildNamesOptions($command, $this->notNames, true);
        $this->buildSizesOptions($command, $this->sizes);
        $this->buildDatesOptions($command, $this->dates);

        if (0 !== $command->execute($output)) {
            throw new \RuntimeException();
        }

        $iterator = new Iterator\FilePathsIterator($output, $dir);

        if ($this->exclude) {
            $iterator = new Iterator\ExcludeDirectoryFilterIterator($iterator, $this->exclude);
        }

        if ($this->contains || $this->notContains) {
            $iterator = new Iterator\FilecontentFilterIterator($iterator, $this->contains, $this->notContains);
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
    public function isSupported()
    {
        $shell = new Shell();

        return $shell->getType() !== Shell::TYPE_WINDOWS
            && $shell->testCommand('find');
    }

    /**
     * @param \Symfony\Component\Finder\Command $command
     * @param string[]                          $names
     * @param bool                              $not
     */
    private function buildNamesOptions(Command $command, array $names, $not = false)
    {
        if (0 === count($names)) {
            return;
        }

        $bits = array();
        foreach ($names as $name) {
            $expr = Expr::create($name);

            if ($expr->isRegex()) {
                $bit = $expr->isCaseSensitive() ? '-regex' : '-iregex';
            } elseif ($expr->isGlob()) {
                $bit = $expr->isCaseSensitive() ? '-name' : '-iname';
            } else {
                continue;
            }

            $bits[] = $bit.' '.escapeshellarg($expr->getBody());
        }

        $command
            ->add('-regextype posix-extended')
            ->add($not ? '-not' : '')
            ->cmd('(')->add(implode(' -or ', $bits))->cmd(')');
    }

    /**
     * @param \Symfony\Component\Finder\Command                       $command
     * @param \Symfony\Component\Finder\Comparator\NumberComparator[] $sizes
     */
    private function buildSizesOptions(Command $command, array $sizes)
    {
        if (0 === count($sizes)) {
            return;
        }

        $bits = array();
        foreach ($sizes as $size) {
            if ('<=' === $size->getOperator()) {
                $bits[] = '-size -'.($size->getTarget()+1).'c';
                continue;
            }

            if ('<' === $size->getOperator()) {
                $bits[] = '-size -'.$size->getTarget().'c';
                continue;
            }

            if ('>=' === $size->getOperator()) {
                $bits[] = '-size +'.($size->getTarget()-1).'c';
                continue;
            }

            if ('>' === $size->getOperator()) {
                $bits[] = '-size +'.$size->getTarget().'c';
                continue;
            }

            if ('!=' === $size->getOperator()) {
                $bits[] = '-size -'.$size->getTarget().'c';
                $bits[] = '-size +'.$size->getTarget().'c';
                continue;
            }

            $bits[] = '-size '.$size->getTarget().'c';
        }

        $command->cmd('(')->add(implode(' -and ', $bits))->cmd(')');
    }

    /**
     * @param \Symfony\Component\Finder\Command                     $command
     * @param \Symfony\Component\Finder\Comparator\DateComparator[] $dates
     */
    private function buildDatesOptions(Command $command, array $dates)
    {
        if (0 === count($dates)) {
            return;
        }

        $bits = array();
        foreach ($dates as $date) {
            $mins = (int) round((time()-$date->getTarget())/60);

            if (0 > $mins) {
                // mtime is in the future
                // we will have no result
                return ' -mmin -0';
            }

            if ('<=' === $date->getOperator()) {
                $bits[] = '-mmin +'.($mins-1);
                continue;
            }

            if ('<' === $date->getOperator()) {
                $bits[] = '-mmin +'.$mins;
                continue;
            }

            if ('>=' === $date->getOperator()) {
                $bits[] = '-mmin -'.($mins+1);
                continue;
            }

            if ('>' === $date->getOperator()) {
                $bits[] = '-mmin -'.$mins;
                continue;
            }

            if ('!=' === $date->getOperator()) {
                $bits[] = '-mmin +'.$mins.' -or -mmin -'.$mins;
                continue;
            }

            $bits[] = '-mmin '.$mins;
        }

        $command->cmd('(')->add(implode(' -and ', $bits))->cmd(')');
    }
}
