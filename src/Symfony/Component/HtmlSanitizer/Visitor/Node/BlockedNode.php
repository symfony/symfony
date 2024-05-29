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
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class BlockedNode implements NodeInterface
{
    private NodeInterface $parentNode;
    private array $children = [];

    public function __construct(NodeInterface $parentNode)
    {
        $this->parentNode = $parentNode;
    }

    public function addChild(NodeInterface $node): void
    {
        $this->children[] = $node;
    }

    public function getParent(): ?NodeInterface
    {
        return $this->parentNode;
    }

    public function render(): string
    {
        $rendered = '';
        foreach ($this->children as $child) {
            $rendered .= $child->render();
        }

        return $rendered;
    }
}
