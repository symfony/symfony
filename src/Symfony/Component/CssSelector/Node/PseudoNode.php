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

/**
 * PseudoNode represents a "selector:ident" node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class PseudoNode implements NodeInterface
{
    static protected $unsupported = array(
        'indeterminate', 'first-line', 'first-letter',
        'selection', 'before', 'after', 'link', 'visited',
        'active', 'focus', 'hover',
    );

    protected $element;
    protected $type;
    protected $ident;

    /**
     * Constructor.
     *
     * @param NodeInterface $element The NodeInterface element
     * @param string $type Node type
     * @param string $ident The ident
     *
     * @throws ParseException When incorrect PseudoNode type is given
     */
    public function __construct($element, $type, $ident)
    {
        $this->element = $element;

        if (!in_array($type, array(':', '::'))) {
            throw new ParseException(sprintf('The PseudoNode type can only be : or :: (%s given).', $type));
        }

        $this->type = $type;
        $this->ident = $ident;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return sprintf('%s[%s%s%s]', __CLASS__, $this->element, $this->type, $this->ident);
    }

    /**
     * {@inheritDoc}
     * @throws ParseException When unsupported or unknown pseudo-class is found
     */
    public function toXpath()
    {
        $elXpath = $this->element->toXpath();

        if (in_array($this->ident, self::$unsupported)) {
            throw new ParseException(sprintf('The pseudo-class %s is unsupported', $this->ident));
        }
        $method = 'xpath_'.str_replace('-', '_', $this->ident);
        if (!method_exists($this, $method)) {
            throw new ParseException(sprintf('The pseudo-class %s is unknown', $this->ident));
        }

        return $this->$method($elXpath);
    }

    /**
     * @param XPathExpr $xpath The XPath expression
     *
     * @return XPathExpr The modified XPath expression
     */
    protected function xpath_checked($xpath)
    {
        // FIXME: is this really all the elements?
        $xpath->addCondition("(@selected or @checked) and (name(.) = 'input' or name(.) = 'option')");

        return $xpath;
    }

    /**
     * @param XPathExpr $xpath The XPath expression
     *
     * @return XPathExpr The modified XPath expression
     *
     * @throws ParseException If this element is the root element
     */
    protected function xpath_root($xpath)
    {
        // if this element is the root element
        throw new ParseException();
    }

    /**
     * Marks this XPath expression as the first child.
     *
     * @param XPathExpr $xpath The XPath expression
     *
     * @return XPathExpr The modified expression
     */
    protected function xpath_first_child($xpath)
    {
        $xpath->addStarPrefix();
        $xpath->addNameTest();
        $xpath->addCondition('position() = 1');

        return $xpath;
    }

    /**
     * Sets the XPath  to be the last child.
     *
     * @param XPathExpr $xpath The XPath expression
     *
     * @return XPathExpr The modified expression
     */
    protected function xpath_last_child($xpath)
    {
        $xpath->addStarPrefix();
        $xpath->addNameTest();
        $xpath->addCondition('position() = last()');

        return $xpath;
    }

    /**
     * Sets the XPath expression to be the first of type.
     *
     * @param XPathExpr $xpath The XPath expression
     *
     * @return XPathExpr The modified expression
     */
    protected function xpath_first_of_type($xpath)
    {
        if ($xpath->getElement() == '*') {
            throw new ParseException('*:first-of-type is not implemented');
        }
        $xpath->addStarPrefix();
        $xpath->addCondition('position() = 1');

        return $xpath;
    }

    /**
     * Sets the XPath expression to be the last of type.
     *
     * @param XPathExpr $xpath The XPath expression
     *
     * @return XPathExpr The modified expression
     *
     * @throws ParseException Because *:last-of-type is not implemented
     */
    protected function xpath_last_of_type($xpath)
    {
        if ($xpath->getElement() == '*') {
            throw new ParseException('*:last-of-type is not implemented');
        }
        $xpath->addStarPrefix();
        $xpath->addCondition('position() = last()');

        return $xpath;
    }

    /**
     * Sets the XPath expression to be the only child.
     *
     * @param XPathExpr $xpath The XPath expression
     *
     * @return XPathExpr The modified expression
     */
    protected function xpath_only_child($xpath)
    {
        $xpath->addNameTest();
        $xpath->addStarPrefix();
        $xpath->addCondition('last() = 1');

        return $xpath;
    }

    /**
     * Sets the XPath expression to be only of type.
     *
     * @param XPathExpr $xpath The XPath expression
     *
     * @return XPathExpr The modified expression
     *
     * @throws ParseException Because *:only-of-type is not implemented
     */
    protected function xpath_only_of_type($xpath)
    {
        if ($xpath->getElement() == '*') {
            throw new ParseException('*:only-of-type is not implemented');
        }
        $xpath->addCondition('last() = 1');

        return $xpath;
    }

    /**
     * undocumented function
     *
     * @param XPathExpr $xpath The XPath expression
     *
     * @return XPathExpr The modified expression
     */
    protected function xpath_empty($xpath)
    {
        $xpath->addCondition('not(*) and not(normalize-space())');

        return $xpath;
    }
}
