<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Visitor\Node;

/**
 * Represents the sanitized version of a DOM node in the sanitized tree.
 *
 * Once the sanitization is done, nodes are rendered into the final output string.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
interface NodeInterface
{
    /**
     * Add a child node to this node.
     */
    public function addChild(self $node): void;

    /**
     * Return the parent node of this node, or null if it has no parent node.
     */
    public function getParent(): ?self;

    /**
     * Render this node as a string, recursively rendering its children as well.
     */
    public function render(): string;
}
