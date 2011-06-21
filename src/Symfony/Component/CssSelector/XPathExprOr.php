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
 * XPathExprOr represents XPath |'d expressions.
 *
 * Note that unfortunately it isn't the union, it's the sum, so duplicate elements will appear.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class XPathExprOr extends XPathExpr
{
    /**
     * Constructor.
     *
     * @param array  $items  The items in the expression.
     * @param string $prefix Optional prefix for the expression.
     */
    public function __construct($items, $prefix = null)
    {
        $this->items = $items;
        $this->prefix = $prefix;
    }

    /**
     * Gets a string representation of this |'d expression.
     *
     * @return string
     */
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
