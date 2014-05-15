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
 * Scanner constraint expression.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Expression
{
    private $value;

    /**
     * Constructor.
     *
     * @param string $pattern
     */
    public function __construct($pattern)
    {
        try {
            $this->value = new Regex($pattern);
        } catch (\InvalidArgumentException $e) {
            $this->value = !(false === strpos($pattern, '?') && false === strpos($pattern, '*') && false === strpos($pattern, '{'))
                ? new Regex(self::globToRegex($pattern))
                : $pattern;
        }
    }

    /**
     * Tests if expression is a regex.
     *
     * @return bool
     */
    public function isRegex()
    {
        return $this->value instanceof Regex;
    }

    /**
     * Returns expression value.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Returns expression as a Regex instance.
     *
     * @return Regex
     */
    public function getRegex()
    {
        return $this->value instanceof Regex ? $this->value : new Regex('~(^|/)'.preg_quote($this->value, '~').'(/|$)~');
    }

    /**
     * Turns a glob pattern into a regex pattern.
     *
     * @param string $pattern
     * @param bool   $strictLeadingDot
     * @param bool   $strictWildcardSlash
     *
     * @return string
     */
    private static function globToRegex($pattern, $strictLeadingDot = true, $strictWildcardSlash = true)
    {
        $firstByte = true;
        $escaping = false;
        $inCurlies = 0;
        $regex = '';
        $sizeGlob = strlen($pattern);
        for ($i = 0; $i < $sizeGlob; $i++) {
            $car = $pattern[$i];
            if ($firstByte) {
                if ($strictLeadingDot && '.' !== $car) {
                    $regex .= '(?=[^\.])';
                }

                $firstByte = false;
            }

            if ('/' === $car) {
                $firstByte = true;
            }

            if ('.' === $car || '(' === $car || ')' === $car || '|' === $car || '+' === $car || '^' === $car || '$' === $car) {
                $regex .= "\\$car";
            } elseif ('*' === $car) {
                $regex .= $escaping ? '\\*' : ($strictWildcardSlash ? '[^/]*' : '.*');
            } elseif ('?' === $car) {
                $regex .= $escaping ? '\\?' : ($strictWildcardSlash ? '[^/]' : '.');
            } elseif ('{' === $car) {
                $regex .= $escaping ? '\\{' : '(';
                if (!$escaping) {
                    ++$inCurlies;
                }
            } elseif ('}' === $car && $inCurlies) {
                $regex .= $escaping ? '}' : ')';
                if (!$escaping) {
                    --$inCurlies;
                }
            } elseif (',' === $car && $inCurlies) {
                $regex .= $escaping ? ',' : '|';
            } elseif ('\\' === $car) {
                if ($escaping) {
                    $regex .= '\\\\';
                    $escaping = false;
                } else {
                    $escaping = true;
                }

                continue;
            } else {
                $regex .= $car;
            }
            $escaping = false;
        }

        return '~^'.$regex.'$~';
    }
}
