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
 * XPathExprOr represents XPath |'d expressions.
 *
 * Note that unfortunately it isn't the union, it's the sum, so duplicate elements will appear.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class XPathExprOr extends XPathExpr
{
    public function __construct($items, $prefix = null)
    {
        $this->items = $items;
        $this->prefix = $prefix;
    }

    public function __toString()
    {
        $prefix = $this->prefix;

        $tmp = array();
        foreach ($this->items as $i) {
            $tmp[] = sprintf('%s%s', $prefix, $i);
        }

        return implode($tmp, ' | ');
    }
}
