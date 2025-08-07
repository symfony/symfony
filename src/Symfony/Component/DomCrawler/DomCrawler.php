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
 * DomCrawler eases navigation of a list of DOM Node objects.
 *
 * Use this class instead of Crawler if you want to use the HTML5 parser and run PHP 8.4 or higher.
 *
 * @extends Crawler<\Dom\Node>
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DomCrawler extends Crawler
{
    /**
     * @param \Dom\NodeList|\Dom\Node|\Dom\Node[]|string|null $node A Node to use as the base for the crawling
     */
    public function __construct(
        \Dom\NodeList|\Dom\Node|array|string|null $node = null,
        ?string $uri = null,
        ?string $baseHref = null,
    ) {
        parent::__construct(null, $uri, $baseHref, false);

        $this->add($node);
    }

    public function getNode(int $position): ?\Dom\Node
    {
        return parent::getNode($position);
    }
}
