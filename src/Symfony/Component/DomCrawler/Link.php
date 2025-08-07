<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler;

/**
 * Link represents an HTML link (an HTML a, area or link tag).
 *
 * @template T of \Dom\Element|\DOMElement
 *
 * @extends AbstractUriElement<T>
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Link extends AbstractUriElement
{
    protected function getRawUri(): string
    {
        return $this->node->getAttribute('href') ?? '';
    }

    /**
     * @param T $node
     */
    protected function setNode(\Dom\Element|\DOMElement $node): void
    {
        if (!\in_array(strtolower($node->nodeName), ['a', 'area', 'link'], true)) {
            throw new \LogicException(\sprintf('Unable to navigate from a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
    }
}
