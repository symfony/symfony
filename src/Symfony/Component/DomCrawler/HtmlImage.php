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
 * HtmlImage represents an HTML image (an HTML img tag).
 */
class HtmlImage extends AbstractHtmlUriElement
{
    public function __construct(\Dom\Element $node, ?string $currentUri = null)
    {
        parent::__construct($node, $currentUri, 'GET');
    }

    protected function getRawUri(): string
    {
        return $this->node->getAttribute('src') ?? '';
    }

    protected function setNode(\Dom\Element $node): void
    {
        if ('img' !== $node->localName) {
            throw new \LogicException(\sprintf('Unable to visualize a "%s" tag.', $node->localName));
        }

        $this->node = $node;
    }
}
