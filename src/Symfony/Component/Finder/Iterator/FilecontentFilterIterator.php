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
 * FilecontentFilterIterator filters files by their contents using patterns (regexps or strings).
 *
 * @author Fabien Potencier  <fabien@symfony.com>
 * @author Włodzimierz Gajda <gajdaw@gajdaw.pl>
 */
class FilecontentFilterIterator extends MultiplePcreFilterIterator
{

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
                $content = file_get_contents($this->getRealpath());
                if (false === $content) {
                    throw new \RuntimeException(sprintf('Error reading file "%s".', $this->getRealpath()));
                }
                if (preg_match($regex, $content)) {
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
                $content = file_get_contents($this->getRealpath());
                if (false === $content) {
                    throw new \RuntimeException(sprintf('Error reading file "%s".', $this->getRealpath()));
                }
                if (preg_match($regex, $content)) {
                    $exclude = true;
                    break;
                }
            }
        } else {
            $exclude = false;
        }

        return $match && !$exclude;
    }

    /**
     * Converts string to regexp if necessary.
     *
     * @param string $str Pattern: string or regexp
     *
     * @return string regexp corresponding to a given string or regexp
     */
    protected function toRegex($str)
    {
        if (preg_match('/^([^a-zA-Z0-9\\\\]).+?\\1[ims]?$/', $str)) {
            return $str;
        }

        return sprintf('/%s/', preg_quote($str, '/'));
    }

}
