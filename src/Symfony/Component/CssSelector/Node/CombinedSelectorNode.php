<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Node;

use Symfony\Component\CssSelector\Exception\ParseException;
use Symfony\Component\CssSelector\XPathExpr;

/**
 * CombinedSelectorNode represents a combinator node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CombinedSelectorNode implements NodeInterface
{
    protected static $methodMapping = array(
        ' ' => 'descendant',
        '>' => 'child',
        '+' => 'direct_adjacent',
        '~' => 'indirect_adjacent',
    );

    protected $selector;
    protected $combinator;
    protected $subselector;

    /**
     * The constructor.
     *
     * @param NodeInterface $selector    The XPath selector
     * @param string        $combinator  The combinator
     * @param NodeInterface $subselector The sub XPath selector
     */
    public function __construct($selector, $combinator, $subselector)
    {
        $this->selector = $selector;
        $this->combinator = $combinator;
        $this->subselector = $subselector;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        $comb = $this->combinator == ' ' ? '<followed>' : $this->combinator;

        return sprintf('%s[%s %s %s]', __CLASS__, $this->selector, $comb, $this->subselector);
    }

    /**
     * {@inheritDoc}
     * @throws ParseException When unknown combinator is found
     */
    public function toXpath()
    {
        if (!isset(self::$methodMapping[$this->combinator])) {
            throw new ParseException(sprintf('Unknown combinator: %s', $this->combinator));
        }

        $method = '_xpath_'.self::$methodMapping[$this->combinator];
        $path = $this->selector->toXpath();

        return $this->$method($path, $this->subselector);
    }

    /**
     * Joins a NodeInterface into the XPath of this object.
     *
     * @param XPathExpr     $xpath The XPath expression for this object
     * @param NodeInterface $sub   The NodeInterface object to add
     *
     * @return XPathExpr An XPath instance
     */
    protected function _xpath_descendant($xpath, $sub)
    {
        // when sub is a descendant in any way of xpath
        $xpath->join('/descendant::', $sub->toXpath());

        return $xpath;
    }

    /**
     * Joins a NodeInterface as a child of this object.
     *
     * @param XPathExpr     $xpath The parent XPath expression
     * @param NodeInterface $sub   The NodeInterface object to add
     *
     * @return XPathExpr An XPath instance
     */
    protected function _xpath_child($xpath, $sub)
    {
        // when sub is an immediate child of xpath
        $xpath->join('/', $sub->toXpath());

        return $xpath;
    }

    /**
     * Joins an XPath expression as an adjacent of another.
     *
     * @param XPathExpr     $xpath The parent XPath expression
     * @param NodeInterface $sub   The adjacent XPath expression
     *
     * @return XPathExpr An XPath instance
     */
    protected function _xpath_direct_adjacent($xpath, $sub)
    {
        // when sub immediately follows xpath
        $xpath->join('/following-sibling::', $sub->toXpath());
        $xpath->addNameTest();
        $xpath->addCondition('position() = 1');

        return $xpath;
    }

    /**
     * Joins an XPath expression as an indirect adjacent of another.
     *
     * @param XPathExpr     $xpath The parent XPath expression
     * @param NodeInterface $sub   The indirect adjacent NodeInterface object
     *
     * @return XPathExpr An XPath instance
     */
    protected function _xpath_indirect_adjacent($xpath, $sub)
    {
        // when sub comes somewhere after xpath as a sibling
        $xpath->join('/following-sibling::', $sub->toXpath());

        return $xpath;
    }
}
