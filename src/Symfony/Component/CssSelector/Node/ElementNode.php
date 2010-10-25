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
 * ElementNode represents a "namespace|element" node.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ElementNode implements NodeInterface
{
    protected $namespace;
    protected $element;

    public function __construct($namespace, $element)
    {
        $this->namespace = $namespace;
        $this->element = $element;
    }

    public function __toString()
    {
        return sprintf('%s[%s]', __CLASS__, $this->formatElement());
    }

    public function formatElement()
    {
        if ($this->namespace == '*') {
            return $this->element;
        }

        return sprintf('%s|%s', $this->namespace, $this->element);
    }

    public function toXpath()
    {
        if ($this->namespace == '*') {
            $el = strtolower($this->element);
        } else {
            // FIXME: Should we lowercase here?
            $el = sprintf('%s:%s', $this->namespace, $this->element);
        }

        return new XPathExpr(null, null, $el);
    }
}
