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
 * HashNode represents a "selector#id" node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HashNode implements NodeInterface
{
    protected $selector;
    protected $id;

    /**
     * Constructor.
     *
     * @param NodeInterface $selector The NodeInterface object
     * @param string        $id       The ID
     */
    public function __construct($selector, $id)
    {
        $this->selector = $selector;
        $this->id = $id;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        return sprintf('%s[%s#%s]', __CLASS__, $this->selector, $this->id);
    }

    /**
     * {@inheritDoc}
     */
    public function toXpath()
    {
        $path = $this->selector->toXpath();
        $path->addCondition(sprintf('@id = %s', XPathExpr::xpathLiteral($this->id)));

        return $path;
    }
}
