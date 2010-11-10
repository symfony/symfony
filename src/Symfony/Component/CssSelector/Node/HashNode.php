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
 * HashNode represents a "selector#id" node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class HashNode implements NodeInterface
{
    protected $selector;
    protected $id;

    public function __construct($selector, $id)
    {
        $this->selector = $selector;
        $this->id = $id;
    }

    public function __toString()
    {
        return sprintf('%s[%s#%s]', __CLASS__, $this->selector, $this->id);
    }

    public function toXpath()
    {
        $path = $this->selector->toXpath();
        $path->addCondition(sprintf('@id = %s', XPathExpr::xpathLiteral($this->id)));

        return $path;
    }
}
