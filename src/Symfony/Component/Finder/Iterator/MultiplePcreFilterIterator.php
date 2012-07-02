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


/**
 * MultiplePcreFilterIterator filters files using patterns (regexps, globs or strings).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class MultiplePcreFilterIterator extends \FilterIterator
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
        $this->matchRegexps = array();
        foreach ($matchPatterns as $pattern) {
            $this->matchRegexps[] = $this->toRegex($pattern);
        }

        $this->noMatchRegexps = array();
        foreach ($noMatchPatterns as $pattern) {
            $this->noMatchRegexps[] = $this->toRegex($pattern);
        }

        parent::__construct($iterator);
    }

    /**
     * Checks whether the string is a regex.
     *
     * @param string $str
     *
     * @return Boolean Whether the given string is a regex
     */
    protected function isRegex($str)
    {
        if (preg_match('/^(.{3,}?)[imsxuADU]*$/', $str, $m)) {
            $start = substr($m[1], 0, 1);
            $end = substr($m[1], -1);

            if ($start === $end) {
                return !preg_match('/[*?[:alnum:] \\\\]/', $start);
            }

            if ($start === '{' && $end === '}') {
                return true;
            }
        }

        return false;
    }

    /**
     * Converts string into regexp.
     *
     * @param string $str Pattern
     *
     * @return string regexp corresponding to a given string
     */
    abstract protected function toRegex($str);
}
