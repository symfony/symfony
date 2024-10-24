<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\NativeCrawler;

/**
 * Image represents an HTML image (an HTML img tag).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class Image extends AbstractUriElement
{
    public function __construct(\DOM\Element $node, ?string $currentUri = null)
    {
        parent::__construct($node, $currentUri, 'GET');
    }

    protected function getRawUri(): string
    {
        return $this->node->getAttribute('src') ?? '';
    }

    protected function setNode(\DOM\Element $node): void
    {
        if ('img' !== strtolower($node->nodeName)) {
            throw new \LogicException(\sprintf('Unable to visualize a "%s" tag.', $node->nodeName));
        }

        $this->node = $node;
    }
}
