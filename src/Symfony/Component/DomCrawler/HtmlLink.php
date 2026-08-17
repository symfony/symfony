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
 * HtmlLink represents an HTML link (an HTML a, area or link tag).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HtmlLink extends AbstractHtmlUriElement
{
    protected function getRawUri(): string
    {
        return $this->node->getAttribute('href') ?? '';
    }

    protected function setNode(\Dom\Element $node): void
    {
        if ('a' !== $node->localName && 'area' !== $node->localName && 'link' !== $node->localName) {
            throw new \LogicException(\sprintf('Unable to navigate from a "%s" tag.', $node->localName));
        }

        $this->node = $node;
    }
}
