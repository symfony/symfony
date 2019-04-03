<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Tests;

use Symfony\Component\DomCrawler\Crawler;

class Html5ParserCrawlerTest extends AbstractCrawlerTest
{
    public function createCrawler($node = null, string $uri = null, string $baseHref = null)
    {
        return new Crawler($node, $uri, $baseHref, true);
    }
}
