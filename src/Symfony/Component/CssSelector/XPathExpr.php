<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector;

/**
 * XPathExpr represents an XPath expression.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class XPathExpr
{
    private $prefix;
    private $path;
    private $element;
    private $condition;
    private $starPrefix;

    /**
     * Constructor.
     *
     * @param string  $prefix     Prefix for the XPath expression.
     * @param string  $path       Actual path of the expression.
     * @param string  $element    The element in the expression.
     * @param string  $condition  A condition for the expression.
     * @param Boolean $starPrefix Indicates whether to use a star prefix.
     */
    public function __construct($prefix = null, $path = null, $element = '*', $condition = null, $starPrefix = false)
    {
        $this->prefix = $prefix;
        $this->path = $path;
        $this->element = $element;
        $this->condition = $condition;
        $this->starPrefix = $starPrefix;
    }

    /**
     * Gets the prefix of this XPath expression.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Gets the path of this XPath expression.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Answers whether this XPath expression has a star prefix.
     *
     * @return Boolean
     */
    public function hasStarPrefix()
    {
        return $this->starPrefix;
    }

    /**
     * Gets the element of this XPath expression.
     *
     * @return string
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * Gets the condition of this XPath expression.
     *
     * @return string
     */
    public function getCondition()
    {
        return $this->condition;
    }

    /**
     * Gets a string representation for this XPath expression.
     *
     * @return string
     */
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

    /**
     * Adds a condition to this XPath expression.
     * Any pre-existent condition will be ANDed to it.
     *
     * @param string $condition The condition to add.
     */
    public function addCondition($condition)
    {
        if ($this->condition) {
            $this->condition = sprintf('%s and (%s)', $this->condition, $condition);
        } else {
            $this->condition = $condition;
        }
    }

    /**
     * Adds a prefix to this XPath expression.
     * It will be prepended to any pre-existent prefixes.
     *
     * @param string $prefix The prefix to add.
     */
    public function addPrefix($prefix)
    {
        if ($this->prefix) {
            $this->prefix = $prefix.$this->prefix;
        } else {
            $this->prefix = $prefix;
        }
    }

    /**
     * Adds a condition to this XPath expression using the name of the element
     * as the desired value.
     * This method resets the element to '*'.
     */
    public function addNameTest()
    {
        if ($this->element == '*') {
            // We weren't doing a test anyway
            return;
        }

        $this->addCondition(sprintf('name() = %s', XPathExpr::xpathLiteral($this->element)));
        $this->element = '*';
    }

    /**
     * Adds a star prefix to this XPath expression.
     * This method will prepend a '*' to the path and set the star prefix flag
     * to true.
     */
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

    /**
     * Joins this XPath expression with $other (another XPath expression) using
     * $combiner to join them.
     *
     * @param string    $combiner The combiner string.
     * @param XPathExpr $other    The other XPath expression to combine with
     *                            this one.
     */
    public function join($combiner, $other)
    {
        $prefix = (string) $this;

        $prefix .= $combiner;
        $path = $other->getPrefix().$other->getPath();

        /* We don't need a star prefix if we are joining to this other
             prefix; so we'll get rid of it */
        if ($other->hasStarPrefix() && '*/' == $path) {
            $path = '';
        }
        $this->prefix = $prefix;
        $this->path = $path;
        $this->element = $other->getElement();
        $this->condition = $other->GetCondition();
    }

    /**
     * Gets an XPath literal for $s.
     *
     * @param mixed $s Can either be a Node\ElementNode or a string.
     *
     * @return string
     */
    public static function xpathLiteral($s)
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
