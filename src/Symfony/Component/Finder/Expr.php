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

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Expr
{
    const TYPE_REGEX = 1;
    const TYPE_GLOB  = 2;

    /**
     * @var string
     */
    private $expr;

    /**
     * @var int
     */
    private $type;

    /**
     * @var string
     */
    private $flags;

    /**
     * @var string
     */
    private $body;

    /**
     * @param string $expr
     */
    public function __construct($expr)
    {
        $this->expr = $expr;

        if (preg_match('/^(.{3,}?)([imsxuADU]*)$/', $this->expr, $m)) {
            $start = substr($m[1], 0, 1);
            $end   = substr($m[1], -1);

            if (($start === $end && !preg_match('/[[:alnum:] \\\\]/', $start)) || ($start === '{' && $end === '}')) {
                $this->type  = self::TYPE_REGEX;
                $this->body  = $m[1];
                $this->flags = $m[2];

                return;
            }
        }

        $this->flags = '';
        $this->body  = $expr;
        $this->type  = self::TYPE_GLOB;
    }

    /**
     * @param string $expr
     *
     * @return Expr
     */
    static public function create($expr)
    {
        return new self($expr);
    }

    /**
     * @return bool
     */
    public function isCaseSensitive()
    {
        return self::TYPE_GLOB === $this->type
            || false === strpos($this->flags, 'i');
    }

    /**
     * @return bool
     */
    public function isRegex()
    {
        return self::TYPE_REGEX === $this->type;
    }

    /**
     * @return bool
     */
    public function isGlob()
    {
        return self::TYPE_GLOB === $this->type;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return mixed
     */
    public function getExpr()
    {
        return $this->expr;
    }

    /**
     * @return string
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param bool $strictLeadingDot
     * @param bool $strictWildcardSlash
     *
     * @return string
     */
    public function getRegex($strictLeadingDot = true, $strictWildcardSlash = true)
    {
        return self::TYPE_REGEX === $this->type
            ? $this->expr
            : $this->globToRegex($this->expr, $strictLeadingDot, $strictWildcardSlash);
    }

    /**
     * @param string $glob
     * @param bool   $strictLeadingDot
     * @param bool   $strictWildcardSlash
     *
     * @return string
     */
    private function globToRegex($glob, $strictLeadingDot = true, $strictWildcardSlash = true)
    {
        $firstByte = true;
        $escaping = false;
        $inCurlies = 0;
        $regex = '';
        $sizeGlob = strlen($glob);
        for ($i = 0; $i < $sizeGlob; $i++) {
            $car = $glob[$i];
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

        return '#^'.$regex.'$#';
    }
}
