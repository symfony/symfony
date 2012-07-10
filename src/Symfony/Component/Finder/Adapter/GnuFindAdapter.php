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
use Symfony\Component\Finder\Iterator\SortableIterator;

/**
 * Shell engine implementation using GNU find command.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class GnuFindAdapter extends AbstractAdapter
{
    /**
     * @var \Symfony\Component\Finder\Shell
     */
    private $shell;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->shell = new Shell();
    }

    /**
     * {@inheritdoc}
     */
    public function searchInDirectory($dir)
    {
        $command = Command::create();

        $find = $command
            ->ins('find')
            ->add('find ')
            ->arg($dir)
            ->add('-noleaf') // -noleaf option is required for filesystems who doesn't follow '.' and '..' convention
            ->add('-regextype posix-extended');

        if ($this->followLinks) {
            $find->add('-follow');
        }

        $find->add('-mindepth')->add($this->minDepth+1);
        // warning! INF < INF => true ; INF == INF => false ; INF === INF => true
        // https://bugs.php.net/bug.php?id=9118
        if (INF !== $this->maxDepth) {
            $find->add('-maxdepth')->add($this->maxDepth+1);
        }

        if (Iterator\FileTypeFilterIterator::ONLY_DIRECTORIES === $this->mode) {
            $find->add('-type d');
        } elseif (Iterator\FileTypeFilterIterator::ONLY_FILES === $this->mode) {
            $find->add('-type f');
        }

        $this->buildNamesCommand($find, $this->names);
        $this->buildNamesCommand($find, $this->notNames, true);
        $this->buildSizesCommand($find, $this->sizes);
        $this->buildDatesCommand($find, $this->dates);

        if (($useGrep = $this->shell->testCommand('grep') && $this->shell->testCommand('xargs')) && ($this->contains || $this->notContains)) {
            $grep = $command->ins('grep');
            $this->buildContainsCommand($grep, $this->contains);
            $this->buildContainsCommand($grep, $this->notContains, true);
        }

        if ($useSort = is_int($this->sort) && $this->shell->testCommand('sort') && $this->shell->testCommand('awk')) {
            $this->buildSortCommand($command, $this->sort);
        }

        if ($this->shell->testCommand('uniq')) {
            $paths = $command->add('| uniq')->execute();
        } else {
            $paths = array_unique($command->execute());
        }

        $iterator = new Iterator\FilePathsIterator($command->execute(), $dir);

        if ($this->exclude) {
            $iterator = new Iterator\ExcludeDirectoryFilterIterator($iterator, $this->exclude);
        }

        if (!$useGrep && ($this->contains || $this->notContains)) {
            $iterator = new Iterator\FilecontentFilterIterator($iterator, $this->contains, $this->notContains);
        }

        if ($this->filters) {
            $iterator = new Iterator\CustomFilterIterator($iterator, $this->filters);
        }

        if (!$useSort && $this->sort) {
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
        return $this->shell->getType() !== Shell::TYPE_WINDOWS
            && $this->shell->testCommand('find');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'gnu_find';
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

    /**
     * @param \Symfony\Component\Finder\Command $command
     * @param array                             $contains
     * @param bool                              $not
     */
    private function buildContainsCommand(Command $command, array $contains, $not = false)
    {
        foreach ($contains as $contain) {
            $expr  = Expr::create($contain);
            $regex = $expr->isRegex()
                ? $expr->getBody()
                : trim(Expr::create($expr->getRegex(false, false))->getBody(), '^$');

            $command
                ->add('| xargs -r grep -I')
                ->add($expr->isCaseSensitive() ? null : '-i')
                ->add($not ? '-L' : '-l')
                ->add('-Ee')->arg($regex);
        }
    }

    private function buildSortCommand(Command $command, $sort)
    {
        switch ($sort) {
            case SortableIterator::SORT_BY_NAME:          $format = null;  break;
            case SortableIterator::SORT_BY_TYPE:          $format = '%y';  break;
            case SortableIterator::SORT_BY_ACCESSED_TIME: $format = '%A@'; break;
            case SortableIterator::SORT_BY_CHANGED_TIME:  $format = '%C@'; break;
            case SortableIterator::SORT_BY_MODIFIED_TIME: $format = '%T@'; break;
            default: throw new \InvalidArgumentException('Unknown sort options: '.$sort.'.');
        }

        $command->get('find')->add('-printf')->arg($format.' %h/%f\\n');
        $command->ins('sort')->add('| sort');
        $command->ins('awk')->add('| awk')->arg('{ print $'.(null === $format ? '1' : '2').' }');
    }
}
