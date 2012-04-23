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
        $command = Command::create()->add('find ')->arg($dir)->add('-noleaf');

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

        $command->ins('names')->ins('notNames');
        $this->buildNamesCommand($command->get('names'), $this->names);
        $this->buildNamesCommand($command->get('notNames'), $this->notNames, true);

        if ($command->get('names')->length() || $command->get('notNames')->length()) {
            $command->get('names')->top('-regextype posix-extended');
        }

        $command->ins('sizes');
        $this->buildSizesCommand($command->get('sizes'), $this->sizes);

        $command->ins('dates');
        $this->buildDatesCommand($command->get('dates'), $this->dates);

        $iterator = new Iterator\FilePathsIterator($command->execute(), $dir);

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
    private function buildNamesCommand(Command $command, array $names, $not = false)
    {
        if (0 === count($names)) {
            return;
        }

        $command->add($not ? '-not' : null)->cmd('(');

        foreach ($names as $i => $name) {
            $expr = Expr::create($name);

            $command
                ->add($i > 0 ? '-or' : null)
                ->add($expr->isRegex()
                    ? ($expr->isCaseSensitive() ? '-regex' : '-iregex')
                    : ($expr->isCaseSensitive() ? '-name' : '-iname')
                )
                ->arg($expr->getBody());
        }

        $command->cmd(')');
    }

    /**
     * @param \Symfony\Component\Finder\Command                       $command
     * @param \Symfony\Component\Finder\Comparator\NumberComparator[] $sizes
     */
    private function buildSizesCommand(Command $command, array $sizes)
    {
        if (0 === count($sizes)) {
            return;
        }

        foreach ($sizes as $i => $size) {
            $command->add($i > 0 ? '-and' : null);

            if ('<=' === $size->getOperator()) {
                $command->add('-size -'.($size->getTarget()+1).'c');
                continue;
            }

            if ('<' === $size->getOperator()) {
                $command->add('-size -'.$size->getTarget().'c');
                continue;
            }

            if ('>=' === $size->getOperator()) {
                $command->add('-size +'.($size->getTarget()-1).'c');
                continue;
            }

            if ('>' === $size->getOperator()) {
                $command->add('-size +'.$size->getTarget().'c');
                continue;
            }

            if ('!=' === $size->getOperator()) {
                $command->add('-size -'.$size->getTarget().'c');
                $command->add('-size +'.$size->getTarget().'c');
                continue;
            }

            $command->add('-size '.$size->getTarget().'c');
        }
    }

    /**
     * @param \Symfony\Component\Finder\Command                     $command
     * @param \Symfony\Component\Finder\Comparator\DateComparator[] $dates
     */
    private function buildDatesCommand(Command $command, array $dates)
    {
        if (0 === count($dates)) {
            return;
        }

        foreach ($dates as $i => $date) {
            $command->add($i > 0 ? '-and' : null);

            $mins = (int) round((time()-$date->getTarget())/60);

            if (0 > $mins) {
                // mtime is in the future
                $command->add(' -mmin -0');
                // we will have no result so we dont need to continue
                return;
            }

            if ('<=' === $date->getOperator()) {
                $command->add('-mmin +'.($mins-1));
                continue;
            }

            if ('<' === $date->getOperator()) {
                $command->add('-mmin +'.$mins);
                continue;
            }

            if ('>=' === $date->getOperator()) {
                $command->add('-mmin -'.($mins+1));
                continue;
            }

            if ('>' === $date->getOperator()) {
                $command->add('-mmin -'.$mins);
                continue;
            }

            if ('!=' === $date->getOperator()) {
                $command->add('-mmin +'.$mins.' -or -mmin -'.$mins);
                continue;
            }

            $command->add('-mmin '.$mins);
        }
    }
}
