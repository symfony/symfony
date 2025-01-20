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

final class CrawlerAnySelectorTextSame extends Constraint
{
    public function __construct(
        private string $selector,
        private string $expectedText,
    ) {
    }

    public function toString(): string
    {
        return \sprintf('has at least a node matching selector "%s" with content "%s"', $this->selector, $this->expectedText);
    }

    protected function matches($other): bool
    {
        if (!$other instanceof Crawler) {
            throw new \InvalidArgumentException(\sprintf('"%s" constraint expected an argument of type "%s", got "%s".', self::class, Crawler::class, get_debug_type($other)));
        }

        $other = $other->filter($this->selector);
        if (!\count($other)) {
            return false;
        }

        $nodes = $other->each(fn (Crawler $node) => trim($node->text(null, true)));

        return \in_array($this->expectedText, $nodes, true);
    }

    protected function failureDescription($other): string
    {
        if (!$other instanceof Crawler) {
            throw new \InvalidArgumentException(\sprintf('"%s" constraint expected an argument of type "%s", got "%s".', self::class, Crawler::class, get_debug_type($other)));
        }

        return 'the Crawler '.$this->toString();
    }
}
