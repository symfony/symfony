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
 * Regex manipulator.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Regex
{
    private $delimiters;
    private $pattern;
    private $options;
    private $starting;
    private $ending;

    /**
     * Constructor.
     *
     * @param string $pattern
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($pattern)
    {
        if (preg_match('/^(.{3,}?)([imsxuADU]*)$/', $pattern, $m)) {
            $start = substr($m[1], 0, 1);
            $end   = substr($m[1], -1);

            if (($start === $end && !preg_match('/[*?[:alnum:] \\\\]/', $start)) || ($start === '{' && $end === '}')) {
                $this->delimiters = array($start, $end);
                $this->pattern = substr($m[1], 1, -1);

                // options are sorted for later comparison
                $options = str_split($m[2]);
                sort($options);
                $this->options = implode('', $options);

                // fixme: find something stronger
                $this->starting = '^' === substr($this->pattern, 0, 1);
                $this->ending = '$' === substr($this->pattern, -1);

                return;
            }
        }

        throw new \InvalidArgumentException(sprintf('Pattern "%s" is not a valid regex.', $pattern));
    }

    /**
     * Returns regex as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return implode($this->pattern, $this->delimiters).$this->options;
    }

    /**
     * Tests if pattern starts with a `^`.
     *
     * @return bool
     */
    public function isStarting()
    {
        return $this->starting;
    }

    /**
     * Tests if pattern ends with a `$`.
     *
     * @return bool
     */
    public function isEnding()
    {
        return $this->ending;
    }

    /**
     * Merges given regexs with an OR condition (grouped by options).
     *
     * @param Regex[] $regexs
     *
     * @return Regex[]
     *
     * @throws \InvalidArgumentException
     */
    public static function mergeWithOr(array $regexs)
    {
        $grouped = array();
        foreach ($regexs as $regex) {
            if (!$regex instanceof self) {
                throw new \InvalidArgumentException('Only Regex instances must be provided.');
            }
            if (!isset($grouped[$regex->options])) {
                $grouped[$regex->options] = array();
            }
            $grouped[$regex->options][] = $regex;
        }

        $merged = array();
        foreach ($grouped as $group) {
            if (count($group) === 1) {
                $merged[] = $group[0];
                continue;
            }
            $first = array_shift($group);
            $first->pattern = sprintf('(%s)', $first->pattern);
            foreach ($group as $regex) {
                $first->pattern .= sprintf('|(%s)', $regex->pattern);
            }
            $merged[] = $first;
        }

        return $merged;
    }
}
