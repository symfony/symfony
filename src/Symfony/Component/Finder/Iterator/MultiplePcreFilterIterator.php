<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\Expression\Expression;
use Symfony\Component\Finder\Expression\Regex;

/**
 * MultiplePcreFilterIterator filters files using patterns (regexps, globs or strings).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class MultiplePcreFilterIterator extends FilterIterator
{
    protected $matchRegexps;
    protected $noMatchRegexps;

    /**
     * Constructor.
     *
     * @param \Iterator $iterator        The Iterator to filter
     * @param array     $matchPatterns   An array of patterns that need to match
     * @param array     $noMatchPatterns An array of patterns that need to not match
     */
    public function __construct(\Iterator $iterator, array $matchPatterns, array $noMatchPatterns)
    {
        $this->matchRegexps = $this->buildRegexps($matchPatterns);
        $this->noMatchRegexps = $this->buildRegexps($noMatchPatterns);

        parent::__construct($iterator);
    }

    /**
     * Checks whether the string is a regex.
     *
     * @param string $str
     *
     * @return bool Whether the given string is a regex
     */
    protected function isRegex($str)
    {
        return Expression::create($str)->isRegex();
    }

    /**
     * Converts string into regexp.
     *
     * @param string $str Pattern
     *
     * @return string regexp corresponding to a given string
     */
    abstract protected function toRegex($str);

    private function buildRegexps($patterns)
    {
        $rxs = array();
        $regexps = array();

        foreach ($patterns as $pattern) {
            $regex = Regex::create($this->toRegex($pattern))->render();

            if (preg_match('/\W([imsxUXJ]*)$/', $regex, $match) && !preg_match('/\\\\[1-9]|\(\?P/', $regex)) {
                $rxs[] = '(?'.$match[1].':'.substr($regex, 1, -strlen($match[0])).')';
            } else {
                $regexps[] = $regex;
            }
        }
        if (1 < count($rxs)) {
            $regexps[] = Regex::BOUNDARY.implode('|', $rxs).Regex::BOUNDARY;
        } elseif ($rxs) {
            $rxs = explode(':', substr($rxs[0], 2, -1), 2);
            $regexps[] = Regex::BOUNDARY.$rxs[1].Regex::BOUNDARY.$rxs[0];
        }

        return $regexps;
    }
}
