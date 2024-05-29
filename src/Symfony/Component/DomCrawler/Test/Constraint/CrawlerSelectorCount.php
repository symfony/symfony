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

final class CrawlerSelectorCount extends Constraint
{
    public function __construct(
        private readonly int $count,
        private readonly string $selector,
    ) {
    }

    public function toString(): string
    {
        return sprintf('selector "%s" count is "%d"', $this->selector, $this->count);
    }

    /**
     * @param Crawler $crawler
     */
    protected function matches($crawler): bool
    {
        return $this->count === \count($crawler->filter($this->selector));
    }

    /**
     * @param Crawler $crawler
     */
    protected function failureDescription($crawler): string
    {
        return sprintf('the Crawler selector "%s" was expected to be found %d time(s) but was found %d time(s)', $this->selector, $this->count, \count($crawler->filter($this->selector)));
    }
}
