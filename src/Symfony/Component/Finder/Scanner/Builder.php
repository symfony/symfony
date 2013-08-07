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

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Builder
{
    private $type = Constraints::TYPE_ALL;
    private $minDepth = 0;
    private $maxDepth = PHP_INT_MAX;
    private $excludedNames = array('.', '..');
    private $pathnameConstraints = array();
    private $filenameConstraints = array();

    public static function create($type, $minDepth, $maxDepth, array $excludedNames)
    {
        $builder = new self();
        $builder->type = $type;
        $builder->minDepth = $minDepth;
        $builder->maxDepth = $maxDepth;
        $builder->excludedNames = array_merge($builder->excludedNames, $excludedNames);

        return $builder;
    }

    public function notName($value)
    {
        $expression = new Expression($value);
        $key = $expression->isRegex() ? 'excluded_patterns' : 'excluded_filenames';

        if (!isset($this->filenameConstraints[$key])) {
            $this->filenameConstraints[$key] = array();
        }

        $this->filenameConstraints[$key][] = $expression->getValue();

        return $this;
    }

    public function notPath($value)
    {
        $expression = new Expression($value);
        $regex = $expression->getRegex();
        $key = $regex->isEnding() ? 'excluded_ending_patterns' : 'excluded_patterns';

        if (!isset($this->pathnameConstraints[$key])) {
            $this->pathnameConstraints[$key] = array();
        }

        $this->pathnameConstraints[$key][] = $regex;

        return $this;
    }

    public function name($value)
    {
        $expression = new Expression($value);
        $key = $expression->isRegex() ? 'included_patterns' : 'included_filenames';

        if (!isset($this->filenameConstraints[$key])) {
            $this->filenameConstraints[$key] = array();
        }

        $this->filenameConstraints[$key][] = $expression->getValue();

        return $this;
    }

    public function path($value)
    {
        $expression = new Expression($value);
        $regex = $expression->getRegex();
        $key = $regex->isEnding() ? 'included_ending_patterns' : 'included_patterns';

        if (!isset($this->pathnameConstraints[$key])) {
            $this->pathnameConstraints[$key] = array();
        }

        $this->pathnameConstraints[$key][] = $regex;

        return $this;
    }

    public function getConstraints()
    {
        foreach ($this->pathnameConstraints as $key => $regexs) {
            $this->pathnameConstraints[$key] = array_map(function (Regex $regex) { return (string) $regex; }, Regex::mergeWithOr($regexs));
        }

        foreach ($this->filenameConstraints as $key => $regexs) {
            if (false !== strpos($key, 'patterns')) {
                $this->filenameConstraints[$key] = array_map(function (Regex $regex) { return (string) $regex; }, Regex::mergeWithOr($regexs));
            }
        }

        return new Constraints(
            $this->type, $this->minDepth, $this->maxDepth,
            $this->excludedNames, $this->pathnameConstraints, $this->filenameConstraints
        );
    }
}
