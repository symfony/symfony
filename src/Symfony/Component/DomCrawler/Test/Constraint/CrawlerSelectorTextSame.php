<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DomCrawler\Test\Constraint;

use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\DomCrawler\Crawler;

final class CrawlerSelectorTextSame extends Constraint
{
    private $selector;
    private $expectedText;

    public function __construct(string $selector, string $expectedText)
    {
        $this->selector = $selector;
        $this->expectedText = $expectedText;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('has a node matching selector "%s" with content "%s"', $this->selector, $this->expectedText);
    }

    /**
     * @param Crawler $crawler
     *
     * {@inheritdoc}
     */
    protected function matches($crawler): bool
    {
        $crawler = $crawler->filter($this->selector);
        if (!\count($crawler)) {
            return false;
        }

        foreach($crawler->getIterator() as $node){
            if($this->expectedText === trim($node->nodeValue)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param Crawler $crawler
     *
     * {@inheritdoc}
     */
    protected function failureDescription($crawler): string
    {
        return 'the Crawler '.$this->toString();
    }
}
