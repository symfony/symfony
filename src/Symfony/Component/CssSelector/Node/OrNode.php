<?php

namespace Symfony\Component\CssSelector\Node;

use Symfony\Component\CssSelector\XPathExprOr;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * OrNode represents a "Or" node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class OrNode implements NodeInterface
{
    protected $items;

    public function __construct($items)
    {
        $this->items = $items;
    }

    public function __toString()
    {
        return sprintf('%s(%s)', __CLASS__, $this->items);
    }

    public function toXpath()
    {
        $paths = array();
        foreach ($this->items as $item) {
            $paths[] = $item->toXpath();
        }

        return new XPathExprOr($paths);
    }
}
