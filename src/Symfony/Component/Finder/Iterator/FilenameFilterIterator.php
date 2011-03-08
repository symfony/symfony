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

use Symfony\Component\Finder\Glob;

/**
 * FilenameFilterIterator filters files by patterns (a regexp, a glob, or a string).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FilenameFilterIterator extends \FilterIterator
{
    private $matchRegexps;
    private $noMatchRegexps;

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
     * Filters the iterator values.
     *
     * @return Boolean true if the value should be kept, false otherwise
     */
    public function accept()
    {
        // should at least match one rule
        if ($this->matchRegexps) {
            $match = false;
            foreach ($this->matchRegexps as $regex) {
                if (preg_match($regex, $this->getFilename())) {
                    $match = true;
                    break;
                }
            }
        } else {
            $match = true;
        }

        // should at least not match one rule to exclude
        if ($this->noMatchRegexps) {
            $exclude = false;
            foreach ($this->noMatchRegexps as $regex) {
                if (preg_match($regex, $this->getFilename())) {
                    $exclude = true;
                    break;
                }
            }
        } else {
            $exclude = false;
        }

        return $match && !$exclude;
    }

    private function toRegex($str)
    {
        if (preg_match('/^([^a-zA-Z0-9\\\\]).+?\\1[ims]?$/', $str)) {
            return $str;
        }

        return Glob::toRegex($str);
    }
}
