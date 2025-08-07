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
 *
 * @template T of \Dom\Element|\DOMElement
 *
 * @extends AbstractUriElement<T>
 */
class Image extends AbstractUriElement
{
    /**
     * @param T $node
     */
    public function __construct(\Dom\Element|\DOMElement $node, ?string $currentUri = null)
    {
        parent::__construct($node, $currentUri, 'GET');
    }

    protected function getRawUri(): string
    {
        return $this->node->getAttribute('src') ?? '';
    }

    /**
     * @param T $node
     */
    protected function setNode(\Dom\Element|\DOMElement $node): void
    {
        if ('img' !== strtolower($node->nodeName)) {
            throw new \LogicException(\sprintf('Unable to visualize a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
    }
}
