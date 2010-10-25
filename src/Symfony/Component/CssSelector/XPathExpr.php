<?php

namespace Symfony\Component\CssSelector;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * XPathExpr represents an XPath expression.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class XPathExpr
{
    protected $prefix;
    protected $path;
    protected $element;
    protected $condition;
    protected $starPrefix;

    public function __construct($prefix = null, $path = null, $element = '*', $condition = null, $starPrefix = false)
    {
        $this->prefix = $prefix;
        $this->path = $path;
        $this->element = $element;
        $this->condition = $condition;
        $this->starPrefix = $starPrefix;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function hasStarPrefix()
    {
        return $this->starPrefix;
    }

    public function getElement()
    {
        return $this->element;
    }

    public function getCondition()
    {
        return $this->condition;
    }

    public function __toString()
    {
        $path = '';
        if (null !== $this->prefix) {
            $path .= $this->prefix;
        }

        if (null !== $this->path) {
            $path .= $this->path;
        }

        $path .= $this->element;

        if ($this->condition) {
            $path .= sprintf('[%s]', $this->condition);
        }

        return $path;
    }

    public function addCondition($condition)
    {
        if ($this->condition) {
            $this->condition = sprintf('%s and (%s)', $this->condition, $condition);
        } else {
            $this->condition = $condition;
        }
    }

    public function addPrefix($prefix)
    {
        if ($this->prefix) {
            $this->prefix = $prefix.$this->prefix;
        } else {
            $this->prefix = $prefix;
        }
    }

    public function addNameTest()
    {
        if ($this->element == '*') {
            // We weren't doing a test anyway
            return;
        }

        $this->addCondition(sprintf('name() = %s', XPathExpr::xpathLiteral($this->element)));
        $this->element = '*';
    }

    public function addStarPrefix()
    {
        /*
        Adds a /* prefix if there is no prefix.  This is when you need
        to keep context's constrained to a single parent.
        */
        if ($this->path) {
            $this->path .= '*/';
        } else {
            $this->path = '*/';
        }

        $this->starPrefix = true;
    }

    public function join($combiner, $other)
    {
        $prefix = (string) $this;

        $prefix .= $combiner;
        $path = $other->getPrefix().$other->getPath();

        /* We don't need a star prefix if we are joining to this other
             prefix; so we'll get rid of it */
        if ($other->hasStarPrefix() && $path == '*/') {
            $path = '';
        }
        $this->prefix = $prefix;
        $this->path = $path;
        $this->element = $other->getElement();
        $this->condition = $other->GetCondition();
    }

    static public function xpathLiteral($s)
    {
        if ($s instanceof Node\ElementNode) {
            // This is probably a symbol that looks like an expression...
            $s = $s->formatElement();
        } else {
            $s = (string) $s;
        }

        if (false === strpos($s, "'")) {
            return sprintf("'%s'", $s);
        }

        if (false === strpos($s, '"')) {
            return sprintf('"%s"', $s);
        }

        $string = $s;
        $parts = array();
        while (true) {
            if (false !== $pos = strpos($string, "'")) {
                $parts[] = sprintf("'%s'", substr($string, 0, $pos));
                $parts[] = "\"'\"";
                $string = substr($string, $pos + 1);
            } else {
                $parts[] = "'$string'";
                break;
            }
        }

        return sprintf('concat(%s)', implode($parts, ', '));
    }
}
