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

final class CrawlerSelectorExists extends Constraint
{
    private $selector;

    public function __construct(string $selector)
    {
        $this->selector = $selector;
    }

    /**
     * {@inheritdoc}
     */
    public function toString(): string
    {
        return sprintf('matches selector "%s"', $this->selector);
    }

    /**
     * @param Crawler $crawler
     *
     * {@inheritdoc}
     */
    protected function matches($crawler): bool
    {
        return 0 < \count($crawler->filter($this->selector));
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
