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
class Expression
{
    private $value;

    public function __construct($pattern)
    {
        try {
            $this->value = new Regex($pattern);
        } catch (\InvalidArgumentException $e) {
            if (self::isGlobPattern($pattern)) {
                $this->value = new Regex(self::globToRegex($pattern));
            } else {
                $this->value = $pattern;
            }
        }
    }

    public function isRegex()
    {
        return $this->value instanceof Regex;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getRegex()
    {
        return $this->value instanceof Regex ? $this->value : new Regex(self::stringToRegex($this->value));
    }

    private static function isGlobPattern($pattern)
    {
        return !(false === strpos($pattern, '?') && false === strpos($pattern, '*') && false === strpos($pattern, '{'));
    }

    private static function stringToRegex($value)
    {
        return '~(^|/)'.preg_quote($value, '#').'(/|$)~';
    }

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
