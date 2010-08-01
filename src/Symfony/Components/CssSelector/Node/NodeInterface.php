<?php

namespace Symfony\Components\CssSelector\Node;

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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface NodeInterface
{
    public function __toString();

    public function toXpath();
}
