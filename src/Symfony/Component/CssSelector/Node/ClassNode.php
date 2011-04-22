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

use Symfony\Component\CssSelector\XPathExpr;

/**
 * ClassNode represents a "selector.className" node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassNode implements NodeInterface
{
    protected $selector;
    protected $className;

    /**
     * The constructor.
     *
     * @param NodeInterface $selector The XPath Selector
     * @param string $className The class name
     */
    public function __construct($selector, $className)
    {
        $this->selector = $selector;
        $this->className = $className;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return sprintf('%s[%s.%s]', __CLASS__, $this->selector, $this->className);
    }

    /**
     * {@inheritDoc}
     */
    public function toXpath()
    {
        $selXpath = $this->selector->toXpath();
        $selXpath->addCondition(sprintf("contains(concat(' ', normalize-space(@class), ' '), %s)", XPathExpr::xpathLiteral(' '.$this->className.' ')));

        return $selXpath;
    }
}
