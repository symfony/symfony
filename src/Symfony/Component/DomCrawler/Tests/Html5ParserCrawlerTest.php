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

class Html5ParserCrawlerTest extends AbstractCrawlerTest
{
    public function getDoctype(): string
    {
        return '<!DOCTYPE html>';
    }

    public function testAddHtml5()
    {
        // Ensure a bug specific to the DOM extension is fixed (see https://github.com/symfony/symfony/issues/28596)
        $crawler = $this->createCrawler();
        $crawler->add($this->getDoctype().'<html><body><h1><p>Foo</p></h1></body></html>');
        $this->assertEquals('Foo', $crawler->filterXPath('//h1')->text(), '->add() adds nodes from a string');
    }
}
