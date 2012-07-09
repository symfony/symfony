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

/**
 * ClassNode represents a "selector.className" node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface NodeInterface
{
    /**
     * Returns a string representation of the object.
     *
     * @return string The string representation
     */
    public function __toString();

    /**
     * @return XPathExpr The XPath expression
     *
     * @throws ParseException When unknown operator is found
     */
    public function toXpath();
}
