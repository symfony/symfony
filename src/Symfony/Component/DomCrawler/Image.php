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
 * @api
 */
class Image extends AbstractUriElement
{
    protected function getRawUri()
    {
        return $this->getNode()->getAttribute('src');
    }

    protected function findNode(\DOMElement $node)
    {
        if ('img' !== $node->nodeName) {
            throw new \LogicException(sprintf('Unable to visualize a "%s" tag.', $node->nodeName));
        }

        return $node;
    }
}
