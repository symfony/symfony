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
use Symfony\Component\Finder\Shell\Shell;
use Symfony\Component\Finder\Expression\Expression;
use Symfony\Component\Finder\Shell\Command;
use Symfony\Component\Finder\Iterator\SortableIterator;
use Symfony\Component\Finder\Comparator\NumberComparator;
use Symfony\Component\Finder\Comparator\DateComparator;

/**
 * Shell engine implementation using GNU find command.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class GnuFindAdapter extends AbstractAdapter
{
    /**
     * @var Shell
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
        // having "/../" in path make find fail
        $dir = realpath($dir);

        // searching directories containing or not containing strings leads to no result
        if (Iterator\FileTypeFilterIterator::ONLY_DIRECTORIES === $this->mode && ($this->contains || $this->notContains)) {
            return new Iterator\FilePathsIterator(array(), $dir);
        }

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

        $this->buildNamesFiltering($find, $this->names);
        $this->buildNamesFiltering($find, $this->notNames, true);
        $this->buildSizesFiltering($find, $this->sizes);
        $this->buildDatesFiltering($find, $this->dates);

        $useGrep = $this->shell->testCommand('grep') && $this->shell->testCommand('xargs');
        $useSort = is_int($this->sort) && $this->shell->testCommand('sort') && $this->shell->testCommand('awk');

        if ($useGrep && ($this->contains || $this->notContains)) {
            $grep = $command->ins('grep');
            $this->buildContentFiltering($grep, $this->contains);
            $this->buildContentFiltering($grep, $this->notContains, true);
        }

        if ($useSort) {
            $this->buildSorting($command, $this->sort);
        }

        $paths = $this->shell->testCommand('uniq') ? $command->add('| uniq')->execute() : array_unique($command->execute());
        $iterator = new Iterator\FilePathsIterator($paths, $dir);

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
        return $this->shell->getType() === Shell::TYPE_UNIX && $this->shell->testCommand('find');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'gnu_find';
    }

    /**
     * @param Command  $command
     * @param string[] $names
     * @param bool     $not
     */
    private function buildNamesFiltering(Command $command, array $names, $not = false)
    {
        if (0 === count($names)) {
            return;
        }

        $command->add($not ? '-not' : null)->cmd('(');

        foreach ($names as $i => $name) {
            $expr = Expression::create($name);

            // Fixes 'not search' and 'fuls path matching' regex problems.
            // - Jokers '.' are replaced by [^/].
            // - We add '[^/]*' before and after regex (if no ^|$ flags are present).
            if ($expr->isRegex()) {
                $regex = $expr->getRegex();
                $regex->prepend($regex->hasStartFlag() ? '/' : '/[^/]*')
                    ->setStartFlag(false)
                    ->setStartJoker(true)
                    ->replaceJokers('[^/]');
                if (!$regex->hasEndFlag() || $regex->hasEndJoker()) {
                    $regex->setEndJoker(false)->append('[^/]*');
                }
            }

            $command
                ->add($i > 0 ? '-or' : null)
                ->add($expr->isRegex()
                    ? ($expr->isCaseSensitive() ? '-regex' : '-iregex')
                    : ($expr->isCaseSensitive() ? '-name' : '-iname')
                )
                ->arg($expr->renderPattern());
        }

        $command->cmd(')');
    }

    /**
     * @param Command            $command
     * @param NumberComparator[] $sizes
     */
    private function buildSizesFiltering(Command $command, array $sizes)
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
     * @param Command          $command
     * @param DateComparator[] $dates
     */
    private function buildDatesFiltering(Command $command, array $dates)
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
     * @param Command $command
     * @param array   $contains
     * @param bool    $not
     */
    private function buildContentFiltering(Command $command, array $contains, $not = false)
    {
        foreach ($contains as $contain) {
            $expr = Expression::create($contain);

            // todo: avoid forking process for each $pattern by using multiple -e options
            $command
                ->add('| xargs -r grep -I')
                ->add($expr->isCaseSensitive() ? null : '-i')
                ->add($not ? '-L' : '-l')
                ->add('-Ee')->arg($expr->renderPattern());
        }
    }

    private function buildSorting(Command $command, $sort)
    {
        switch ($sort) {
            case SortableIterator::SORT_BY_NAME:
                $format = null;
                break;
            case SortableIterator::SORT_BY_TYPE:
                $format = '%y';
                break;
            case SortableIterator::SORT_BY_ACCESSED_TIME:
                $format = '%A@';
                break;
            case SortableIterator::SORT_BY_CHANGED_TIME:
                $format = '%C@';
                break;
            case SortableIterator::SORT_BY_MODIFIED_TIME:
                $format = '%T@';
                break;
            default:
                throw new \InvalidArgumentException('Unknown sort options: '.$sort.'.');
        }

        $command->get('find')->add('-printf')->arg($format.' %h/%f\\n');
        $command->ins('sort')->add('| sort');
        $command->ins('awk')->add('| awk')->arg('{ print $'.(null === $format ? '1' : '2').' }');
    }
}
