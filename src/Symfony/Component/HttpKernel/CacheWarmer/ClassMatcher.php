<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

/**
 * Default implementation of the ClassMatcherInterface.
 *
 * This implementation uses single wildcards for any character other than backslashes
 * and double wildcards for any character.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class ClassMatcher implements ClassMatcherInterface
{
    /**
     * {@inheritdoc}
     */
    public function match(array $classes, array $patterns)
    {
        $matched = array();

        // Explicit classes declared in the patterns are returned directly
        foreach ($patterns as $key => $pattern) {
            if (substr($pattern, -1) !== '\\' && false === strpos($pattern, '*')) {
                unset($patterns[$key]);
                $matched[] = ltrim($pattern, '\\');
            }
        }

        // Match patterns with the classes list
        $regexps = $this->patternsToRegexps($patterns);

        foreach ($classes as $class) {
            $class = ltrim($class, '\\');

            if ($this->matchAnyRegexp($class, $regexps)) {
                $matched[] = $class;
            }
        }

        return $matched;
    }

    private function patternsToRegexps($patterns)
    {
        $regexps = array();

        foreach ($patterns as $pattern) {
            // Escape user input
            $regex = preg_quote(ltrim($pattern, '\\'));

            // Wildcards * and **
            $regex = strtr($regex, array('\\*\\*' => '.*?', '\\*' => '[^\\\\]*?'));

            // If this class does not end by a slash, anchor the end
            if (substr($regex, -1) !== '\\') {
                $regex .= '$';
            }

            $regexps[] = '{^\\\\'.$regex.'}';
        }

        return $regexps;
    }

    private function matchAnyRegexp($class, $regexps)
    {
        $blacklisted = false !== strpos($class, 'Test');

        foreach ($regexps as $regex) {
            if ($blacklisted && false === strpos($regex, 'Test')) {
                continue;
            }

            if (preg_match($regex, '\\'.$class)) {
                return true;
            }
        }

        return false;
    }
}
