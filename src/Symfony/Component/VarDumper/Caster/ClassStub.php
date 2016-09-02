<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

/**
 * Represents a PHP class identifier.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ClassStub extends ConstStub
{
    /**
     * Constructor.
     *
     * @param string   A PHP identifier, e.g. a class, method, interface, etc. name
     * @param callable The callable targeted by the identifier when it is ambiguous or not a real PHP identifier
     */
    public function __construct($identifier, $callable = null)
    {
        $this->value = $identifier;

        if (0 < $i = strrpos($identifier, '\\')) {
            $this->attr['ellipsis'] = strlen($identifier) - $i;
        }

        if (null !== $callable) {
            if ($callable instanceof \Closure) {
                $r = new \ReflectionFunction($callable);
            } elseif (is_object($callable)) {
                $r = new \ReflectionMethod($callable, '__invoke');
            } elseif (is_array($callable)) {
                $r = new \ReflectionMethod($callable[0], $callable[1]);
            } elseif (false !== $i = strpos($callable, '::')) {
                $r = new \ReflectionMethod(substr($callable, 0, $i), substr($callable, 2 + $i));
            } else {
                $r = new \ReflectionFunction($callable);
            }
        } elseif (false !== $i = strpos($identifier, '::')) {
            $r = new \ReflectionMethod(substr($identifier, 0, $i), substr($identifier, 2 + $i));
        } else {
            $r = new \ReflectionClass($identifier);
        }

        if ($f = $r->getFileName()) {
            $this->attr['file'] = $f;
            $this->attr['line'] = $r->getStartLine() - substr_count($r->getDocComment(), "\n");
        }
    }

    public static function wrapCallable($callable)
    {
        if (is_object($callable) || !is_callable($callable)) {
            return $callable;
        }

        if (!is_array($callable)) {
            $callable = new static($callable);
        } elseif (is_string($callable[0])) {
            $callable[0] = new static($callable[0]);
        } else {
            $callable[1] = new static($callable[1], $callable);
        }

        return $callable;
    }
}
