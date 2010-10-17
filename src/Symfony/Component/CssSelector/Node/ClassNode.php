<?php

namespace Symfony\Component\CssSelector\Node;

use Symfony\Component\CssSelector\XPathExpr;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * ClassNode represents a "selector.className" node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ClassNode implements NodeInterface
{
    protected $selector;
    protected $className;

    public function __construct($selector, $className)
    {
        $this->selector = $selector;
        $this->className = $className;
    }

    public function __toString()
    {
        return sprintf('%s[%s.%s]', __CLASS__, $this->selector, $this->className);
    }

    public function toXpath()
    {
        $selXpath = $this->selector->toXpath();
        $selXpath->addCondition(sprintf("contains(concat(' ', normalize-space(@class), ' '), %s)", XPathExpr::xpathLiteral(' '.$this->className.' ')));

        return $selXpath;
    }
}
