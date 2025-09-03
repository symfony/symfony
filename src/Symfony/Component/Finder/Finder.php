<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder;

use Symfony\Component\Finder\Comparator\DateComparator;
use Symfony\Component\Finder\Comparator\NumberComparator;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Iterator\CustomFilterIterator;
use Symfony\Component\Finder\Iterator\DateRangeFilterIterator;
use Symfony\Component\Finder\Iterator\DepthRangeFilterIterator;
use Symfony\Component\Finder\Iterator\ExcludeDirectoryFilterIterator;
use Symfony\Component\Finder\Iterator\FilecontentFilterIterator;
use Symfony\Component\Finder\Iterator\FilenameFilterIterator;
use Symfony\Component\Finder\Iterator\LazyIterator;
use Symfony\Component\Finder\Iterator\SizeRangeFilterIterator;
use Symfony\Component\Finder\Iterator\SortableIterator;

/**
 * Finder allows to build rules to find files and directories.
 *
 * It is a thin wrapper around several specialized iterator classes.
 *
 * All rules may be invoked several times.
 *
 * All methods return the current Finder object to allow chaining:
 *
 *     $finder = Finder::create()->files()->name('*.php')->in(__DIR__);
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @implements \IteratorAggregate<string, SplFileInfo>
 */
class Finder implements \IteratorAggregate, \Countable
{
    public const IGNORE_VCS_FILES = 1;
    public const IGNORE_DOT_FILES = 2;
    public const IGNORE_VCS_IGNORED_FILES = 4;

    private int $mode = 0;
    private array $names = [];
    private array $notNames = [];
    private array $exclude = [];
    private array $filters = [];
    private array $pruneFilters = [];
    private array $depths = [];
    private array $sizes = [];
    private bool $followLinks = false;
    private bool $reverseSorting = false;
    private \Closure|int|false $sort = false;
    private int $ignore = 0;
    private array $dirs = [];
    private array $dates = [];
    private array $iterators = [];
    private array $contains = [];
    private array $notContains = [];
    private array $paths = [];
    private array $notPaths = [];
    private bool $ignoreUnreadableDirs = false;

    private bool $immutable = false;

    private static array $vcsPatterns = ['.svn', '_svn', 'CVS', '_darcs', '.arch-params', '.monotone', '.bzr', '.git', '.hg'];

    public function __construct()
    {
        $this->ignore = static::IGNORE_VCS_FILES | static::IGNORE_DOT_FILES;
    }

    /**
     * Creates a new Finder.
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Makes the current Finder immutable or not.
     *
     * An immutable Finder returns a clone of itself when a modification method is called.
     * This allows to keep a "base" finder as a prototype and create several modified
     * versions of it.
     */
    public function immutable(bool $immutable = true): static
    {
        if ($this->immutable === $immutable) {
            return $this;
        }

        $this->immutable = $immutable;

        return clone $this;
    }

    /**
     * Restricts the matching to directories only.
     *
     * @return $this
     */
    public function directories(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->mode = Iterator\FileTypeFilterIterator::ONLY_DIRECTORIES;

        return $finder;
    }

    /**
     * Restricts the matching to files only.
     *
     * @return $this
     */
    public function files(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->mode = Iterator\FileTypeFilterIterator::ONLY_FILES;

        return $finder;
    }

    /**
     * Adds tests for the directory depth.
     *
     * Usage:
     *
     *     $finder->depth('> 1') // the Finder will start matching at level 1.
     *     $finder->depth('< 3') // the Finder will descend at most 3 levels of directories below the starting point.
     *     $finder->depth(['>= 1', '< 3'])
     *
     * @param string|int|string[]|int[] $levels The depth level expression or an array of depth levels
     *
     * @return $this
     *
     * @see DepthRangeFilterIterator
     * @see NumberComparator
     */
    public function depth(string|int|array $levels): static
    {
        $finder = $this->immutable ? clone $this : $this;
        foreach ((array) $levels as $level) {
            $finder->depths[] = new NumberComparator($level);
        }

        return $finder;
    }

    /**
     * Adds tests for file dates (last modified).
     *
     * The date must be something that strtotime() is able to parse:
     *
     *     $finder->date('since yesterday');
     *     $finder->date('until 2 days ago');
     *     $finder->date('> now - 2 hours');
     *     $finder->date('>= 2005-10-15');
     *     $finder->date(['>= 2005-10-15', '<= 2006-05-27']);
     *
     * @param string|string[] $dates A date range string or an array of date ranges
     *
     * @return $this
     *
     * @see strtotime
     * @see DateRangeFilterIterator
     * @see DateComparator
     */
    public function date(string|array $dates): static
    {
        $finder = $this->immutable ? clone $this : $this;
        foreach ((array) $dates as $date) {
            $finder->dates[] = new DateComparator($date);
        }

        return $finder;
    }

    /**
     * Adds rules that files must match.
     *
     * You can use patterns (delimited with / sign), globs or simple strings.
     *
     *     $finder->name('/\.php$/')
     *     $finder->name('*.php') // same as above, without dot files
     *     $finder->name('test.php')
     *     $finder->name(['test.py', 'test.php'])
     *
     * @param string|string[] $patterns A pattern (a regexp, a glob, or a string) or an array of patterns
     *
     * @return $this
     *
     * @see FilenameFilterIterator
     */
    public function name(string|array $patterns): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->names = array_merge($finder->names, (array) $patterns);

        return $finder;
    }

    /**
     * Adds rules that files must not match.
     *
     * @param string|string[] $patterns A pattern (a regexp, a glob, or a string) or an array of patterns
     *
     * @return $this
     *
     * @see FilenameFilterIterator
     */
    public function notName(string|array $patterns): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->notNames = array_merge($finder->notNames, (array) $patterns);

        return $finder;
    }

    /**
     * Adds tests that file contents must match.
     *
     * Strings or PCRE patterns can be used:
     *
     *     $finder->contains('Lorem ipsum')
     *     $finder->contains('/Lorem ipsum/i')
     *     $finder->contains(['dolor', '/ipsum/i'])
     *
     * @param string|string[] $patterns A pattern (string or regexp) or an array of patterns
     *
     * @return $this
     *
     * @see FilecontentFilterIterator
     */
    public function contains(string|array $patterns): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->contains = array_merge($finder->contains, (array) $patterns);

        return $finder;
    }

    /**
     * Adds tests that file contents must not match.
     *
     * Strings or PCRE patterns can be used:
     *
     *     $finder->notContains('Lorem ipsum')
     *     $finder->notContains('/Lorem ipsum/i')
     *     $finder->notContains(['lorem', '/dolor/i'])
     *
     * @param string|string[] $patterns A pattern (string or regexp) or an array of patterns
     *
     * @return $this
     *
     * @see FilecontentFilterIterator
     */
    public function notContains(string|array $patterns): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->notContains = array_merge($finder->notContains, (array) $patterns);

        return $finder;
    }

    /**
     * Adds rules that filenames must match.
     *
     * You can use patterns (delimited with / sign) or simple strings.
     *
     *     $finder->path('some/special/dir')
     *     $finder->path('/some\/special\/dir/') // same as above
     *     $finder->path(['some dir', 'another/dir'])
     *
     * Use only / as dirname separator.
     *
     * @param string|string[] $patterns A pattern (a regexp or a string) or an array of patterns
     *
     * @return $this
     *
     * @see FilenameFilterIterator
     */
    public function path(string|array $patterns): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->paths = array_merge($finder->paths, (array) $patterns);

        return $finder;
    }

    /**
     * Adds rules that filenames must not match.
     *
     * You can use patterns (delimited with / sign) or simple strings.
     *
     *     $finder->notPath('some/special/dir')
     *     $finder->notPath('/some\/special\/dir/') // same as above
     *     $finder->notPath(['some/file.txt', 'another/file.log'])
     *
     * Use only / as dirname separator.
     *
     * @param string|string[] $patterns A pattern (a regexp or a string) or an array of patterns
     *
     * @return $this
     *
     * @see FilenameFilterIterator
     */
    public function notPath(string|array $patterns): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->notPaths = array_merge($finder->notPaths, (array) $patterns);

        return $finder;
    }

    /**
     * Adds tests for file sizes.
     *
     *     $finder->size('> 10K');
     *     $finder->size('<= 1Ki');
     *     $finder->size(4);
     *     $finder->size(['> 10K', '< 20K'])
     *
     * @param string|int|string[]|int[] $sizes A size range string or an integer or an array of size ranges
     *
     * @return $this
     *
     * @see SizeRangeFilterIterator
     * @see NumberComparator
     */
    public function size(string|int|array $sizes): static
    {
        $finder = $this->immutable ? clone $this : $this;
        foreach ((array) $sizes as $size) {
            $finder->sizes[] = new NumberComparator($size);
        }

        return $finder;
    }

    /**
     * Excludes directories.
     *
     * Directories passed as argument must be relative to the ones defined with the `in()` method. For example:
     *
     *     $finder->in(__DIR__)->exclude('ruby');
     *
     * @param string|array $dirs A directory path or an array of directories
     *
     * @return $this
     *
     * @see ExcludeDirectoryFilterIterator
     */
    public function exclude(string|array $dirs): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->exclude = array_merge($finder->exclude, (array) $dirs);

        return $finder;
    }

    /**
     * Excludes "hidden" directories and files (starting with a dot).
     *
     * This option is enabled by default.
     *
     * @return $this
     *
     * @see ExcludeDirectoryFilterIterator
     */
    public function ignoreDotFiles(bool $ignoreDotFiles): static
    {
        $finder = $this->immutable ? clone $this : $this;
        if ($ignoreDotFiles) {
            $finder->ignore |= static::IGNORE_DOT_FILES;
        } else {
            $finder->ignore &= ~static::IGNORE_DOT_FILES;
        }

        return $finder;
    }

    /**
     * Forces the finder to ignore version control directories.
     *
     * This option is enabled by default.
     *
     * @return $this
     *
     * @see ExcludeDirectoryFilterIterator
     */
    public function ignoreVCS(bool $ignoreVCS): static
    {
        $finder = $this->immutable ? clone $this : $this;
        if ($ignoreVCS) {
            $finder->ignore |= static::IGNORE_VCS_FILES;
        } else {
            $finder->ignore &= ~static::IGNORE_VCS_FILES;
        }

        return $finder;
    }

    /**
     * Forces Finder to obey .gitignore and ignore files based on rules listed there.
     *
     * This option is disabled by default.
     *
     * @return $this
     */
    public function ignoreVCSIgnored(bool $ignoreVCSIgnored): static
    {
        $finder = $this->immutable ? clone $this : $this;
        if ($ignoreVCSIgnored) {
            $finder->ignore |= static::IGNORE_VCS_IGNORED_FILES;
        } else {
            $finder->ignore &= ~static::IGNORE_VCS_IGNORED_FILES;
        }

        return $finder;
    }

    /**
     * Adds VCS patterns.
     *
     * @see ignoreVCS()
     *
     * @param string|string[] $pattern VCS patterns to ignore
     */
    public static function addVCSPattern(string|array $pattern): void
    {
        foreach ((array) $pattern as $p) {
            self::$vcsPatterns[] = $p;
        }

        self::$vcsPatterns = array_unique(self::$vcsPatterns);
    }

    /**
     * Sorts files and directories by an anonymous function.
     *
     * The anonymous function receives two \SplFileInfo instances to compare.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sort(\Closure $closure): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->sort = $closure;

        return $finder;
    }

    /**
     * Sorts files and directories by extension.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sortByExtension(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->sort = SortableIterator::SORT_BY_EXTENSION;

        return $finder;
    }

    /**
     * Sorts files and directories by name.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sortByName(bool $useNaturalSort = false): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->sort = $useNaturalSort ? SortableIterator::SORT_BY_NAME_NATURAL : SortableIterator::SORT_BY_NAME;

        return $finder;
    }

    /**
     * Sorts files and directories by name case insensitive.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sortByCaseInsensitiveName(bool $useNaturalSort = false): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->sort = $useNaturalSort ? SortableIterator::SORT_BY_NAME_NATURAL_CASE_INSENSITIVE : SortableIterator::SORT_BY_NAME_CASE_INSENSITIVE;

        return $finder;
    }

    /**
     * Sorts files and directories by size.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sortBySize(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->sort = SortableIterator::SORT_BY_SIZE;

        return $finder;
    }

    /**
     * Sorts files and directories by type (directories before files), then by name.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sortByType(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->sort = SortableIterator::SORT_BY_TYPE;

        return $finder;
    }

    /**
     * Sorts files and directories by the last accessed time.
     *
     * This is the time that the file was last accessed, read or written to.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sortByAccessedTime(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->sort = SortableIterator::SORT_BY_ACCESSED_TIME;

        return $finder;
    }

    /**
     * Reverses the sorting.
     *
     * @return $this
     */
    public function reverseSorting(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->reverseSorting = true;

        return $finder;
    }

    /**
     * Sorts files and directories by the last inode changed time.
     *
     * This is the time that the inode information was last modified (permissions, owner, group or other metadata).
     *
     * On Windows, since inode is not available, changed time is actually the file creation time.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sortByChangedTime(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->sort = SortableIterator::SORT_BY_CHANGED_TIME;

        return $finder;
    }

    /**
     * Sorts files and directories by the last modified time.
     *
     * This is the last time the actual contents of the file were last modified.
     *
     * This can be slow as all the matching files and directories must be retrieved for comparison.
     *
     * @return $this
     *
     * @see SortableIterator
     */
    public function sortByModifiedTime(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->sort = SortableIterator::SORT_BY_MODIFIED_TIME;

        return $finder;
    }

    /**
     * Filters the iterator with an anonymous function.
     *
     * The anonymous function receives a \SplFileInfo and must return false
     * to remove files.
     *
     * @param \Closure(SplFileInfo): bool $closure
     * @param bool                        $prune   Whether to skip traversing directories further
     *
     * @return $this
     *
     * @see CustomFilterIterator
     */
    public function filter(\Closure $closure, bool $prune = false): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->filters[] = $closure;

        if ($prune) {
            $finder->pruneFilters[] = $closure;
        }

        return $finder;
    }

    /**
     * Forces the following of symlinks.
     *
     * @return $this
     */
    public function followLinks(): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->followLinks = true;

        return $finder;
    }

    /**
     * Tells finder to ignore unreadable directories.
     *
     * By default, scanning unreadable directories content throws an AccessDeniedException.
     *
     * @return $this
     */
    public function ignoreUnreadableDirs(bool $ignore = true): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $finder->ignoreUnreadableDirs = $ignore;

        return $finder;
    }

    /**
     * Searches files and directories which match defined rules.
     *
     * @param string|string[] $dirs A directory path or an array of directories
     *
     * @return $this
     *
     * @throws DirectoryNotFoundException if one of the directories does not exist
     */
    public function in(string|array $dirs): static
    {
        $finder = $this->immutable ? clone $this : $this;
        $resolvedDirs = [];

        foreach ((array) $dirs as $dir) {
            if (is_dir($dir)) {
                $resolvedDirs[] = [$this->normalizeDir($dir)];
            } elseif ($glob = glob($dir, (\defined('GLOB_BRACE') ? \GLOB_BRACE : 0) | \GLOB_ONLYDIR | \GLOB_NOSORT)) {
                sort($glob);
                $resolvedDirs[] = array_map($this->normalizeDir(...), $glob);
            } else {
                throw new DirectoryNotFoundException(\sprintf('The "%s" directory does not exist.', $dir));
            }
        }

        $finder->dirs = array_merge($finder->dirs, ...$resolvedDirs);

        return $finder;
    }

    /**
     * Returns an Iterator for the current Finder configuration.
     *
     * This method implements the IteratorAggregate interface.
     *
     * @return \Iterator<string, SplFileInfo>
     *
     * @throws \LogicException if the in() method has not been called
     */
    public function getIterator(): \Iterator
    {
        if (0 === \count($this->dirs) && 0 === \count($this->iterators)) {
            throw new \LogicException('You must call one of in() or append() methods before iterating over a Finder.');
        }

        if (1 === \count($this->dirs) && 0 === \count($this->iterators)) {
            $iterator = $this->searchInDirectory($this->dirs[0]);

            if ($this->sort || $this->reverseSorting) {
                $iterator = (new SortableIterator($iterator, $this->sort, $this->reverseSorting))->getIterator();
            }

            return $iterator;
        }

        $iterator = new \AppendIterator();
        foreach ($this->dirs as $dir) {
            $iterator->append(new \IteratorIterator(new LazyIterator(fn () => $this->searchInDirectory($dir))));
        }

        foreach ($this->iterators as $it) {
            $iterator->append($it);
        }

        if ($this->sort || $this->reverseSorting) {
            $iterator = (new SortableIterator($iterator, $this->sort, $this->reverseSorting))->getIterator();
        }

        return $iterator;
    }

    /**
     * Appends an existing set of files/directories to the finder.
     *
     * The set can be another Finder, an Iterator, an IteratorAggregate, or even a plain array.
     *
     * @return $this
     */
    public function append(iterable $iterator): static
    {
        $finder = $this->immutable ? clone $this : $this;
        if ($iterator instanceof \IteratorAggregate) {
            $finder->iterators[] = $iterator->getIterator();
        } elseif ($iterator instanceof \Iterator) {
            $finder->iterators[] = $iterator;
        } else {
            $it = new \ArrayIterator();
            foreach ($iterator as $file) {
                $file = $file instanceof \SplFileInfo ? $file : new \SplFileInfo($file);
                $it[$file->getPathname()] = $file;
            }
            $finder->iterators[] = $it;
        }

        return $finder;
    }

    /**
     * Check if any results were found.
     */
    public function hasResults(): bool
    {
        foreach ($this->getIterator() as $_) {
            return true;
        }

        return false;
    }

    /**
     * Counts all the results collected by the iterators.
     */
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    private function searchInDirectory(string $dir): \Iterator
    {
        $exclude = $this->exclude;
        $notPaths = $this->notPaths;

        if ($this->pruneFilters) {
            $exclude = array_merge($exclude, $this->pruneFilters);
        }

        if (static::IGNORE_VCS_FILES === (static::IGNORE_VCS_FILES & $this->ignore)) {
            $exclude = array_merge($exclude, self::$vcsPatterns);
        }

        if (static::IGNORE_DOT_FILES === (static::IGNORE_DOT_FILES & $this->ignore)) {
            $notPaths[] = '#(^|/)\..+(/|$)#';
        }

        $minDepth = 0;
        $maxDepth = \PHP_INT_MAX;

        foreach ($this->depths as $comparator) {
            switch ($comparator->getOperator()) {
                case '>':
                    $minDepth = $comparator->getTarget() + 1;
                    break;
                case '>=':
                    $minDepth = $comparator->getTarget();
                    break;
                case '<':
                    $maxDepth = $comparator->getTarget() - 1;
                    break;
                case '<=':
                    $maxDepth = $comparator->getTarget();
                    break;
                default:
                    $minDepth = $maxDepth = $comparator->getTarget();
            }
        }

        $flags = \RecursiveDirectoryIterator::SKIP_DOTS;

        if ($this->followLinks) {
            $flags |= \RecursiveDirectoryIterator::FOLLOW_SYMLINKS;
        }

        $iterator = new Iterator\RecursiveDirectoryIterator($dir, $flags, $this->ignoreUnreadableDirs);

        if ($exclude) {
            $iterator = new ExcludeDirectoryFilterIterator($iterator, $exclude);
        }

        $iterator = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::SELF_FIRST);

        if ($minDepth > 0 || $maxDepth < \PHP_INT_MAX) {
            $iterator = new DepthRangeFilterIterator($iterator, $minDepth, $maxDepth);
        }

        if ($this->mode) {
            $iterator = new Iterator\FileTypeFilterIterator($iterator, $this->mode);
        }

        if ($this->names || $this->notNames) {
            $iterator = new FilenameFilterIterator($iterator, $this->names, $this->notNames);
        }

        if ($this->contains || $this->notContains) {
            $iterator = new FilecontentFilterIterator($iterator, $this->contains, $this->notContains);
        }

        if ($this->sizes) {
            $iterator = new SizeRangeFilterIterator($iterator, $this->sizes);
        }

        if ($this->dates) {
            $iterator = new DateRangeFilterIterator($iterator, $this->dates);
        }

        if ($this->filters) {
            $iterator = new CustomFilterIterator($iterator, $this->filters);
        }

        if ($this->paths || $notPaths) {
            $iterator = new Iterator\PathFilterIterator($iterator, $this->paths, $notPaths);
        }

        if (static::IGNORE_VCS_IGNORED_FILES === (static::IGNORE_VCS_IGNORED_FILES & $this->ignore)) {
            $iterator = new Iterator\VcsIgnoredFilterIterator($iterator, $dir);
        }

        return $iterator;
    }

    /**
     * Normalizes given directory names by removing trailing slashes.
     *
     * Excluding: (s)ftp:// or ssh2.(s)ftp:// wrapper
     */
    private function normalizeDir(string $dir): string
    {
        if ('/' === $dir) {
            return $dir;
        }

        $dir = rtrim($dir, '/'.\DIRECTORY_SEPARATOR);

        if (preg_match('#^(ssh2\.)?s?ftp://#', $dir)) {
            $dir .= '/';
        }

        return $dir;
    }
}
