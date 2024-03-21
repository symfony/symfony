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
 * Image represents an HTML image (an HTML img tag).
 */
class Image extends AbstractUriElement
{
    protected function getRawUri(): string
    {
        return $this->domNode->getAttribute('src') ?? '';
    }

    /**
     * @deprecated since Symfony 7.1, use `setDomNode()` instead
     */
    protected function setNode(\DOMElement $node): void
    {
        if ('img' !== $node->nodeName) {
            throw new \LogicException(\sprintf('Unable to visualize a "%s" tag.', $node->nodeName));
        }

        trigger_deprecation('symfony/dom-crawler', '7.1', 'The "%s()" method is deprecated, use "%s::setDomNode()" instead.', __METHOD__, __CLASS__);

        $this->setDomNode($node);
    }

    protected function setDomNode(\DOMElement|\DOM\Element $node): void
    {
        $nodeName = strtolower($node->nodeName);
        if ('img' !== $nodeName) {
            throw new \LogicException(sprintf('Unable to visualize a "%s" tag.', $nodeName));
        }

        $this->domNode = $node;
        if ($node instanceof \DOMElement) {
            $this->node = $node;
        }
    }
}
