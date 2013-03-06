<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Parser;

use Symfony\Component\CssSelector\Node\NodeInterface;

/**
 * CSS selector parser interface.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
interface ParserInterface
{
    /**
     * Parses given selector source into an array of tokens.
     *
     * @param string $source
     *
     * @return NodeInterface[]
     */
    public function parse($source);
}
