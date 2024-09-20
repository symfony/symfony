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
final class DocumentNode implements NodeInterface
{
    private array $children = [];

    public function addChild(NodeInterface $node): void
    {
        $this->children[] = $node;
    }

    public function getParent(): ?NodeInterface
    {
        return null;
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
