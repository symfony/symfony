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

final class CrawlerAnySelectorTextContains extends Constraint
{
    private bool $hasNode = false;

    public function __construct(
        private string $selector,
        private string $expectedText,
    ) {
    }

    public function toString(): string
    {
        if ($this->hasNode) {
            return \sprintf('the text of any node matching selector "%s" contains "%s"', $this->selector, $this->expectedText);
        }

        return \sprintf('the Crawler has a node matching selector "%s"', $this->selector);
    }

    protected function matches($other): bool
    {
        if (!$other instanceof Crawler) {
            throw new \InvalidArgumentException(\sprintf('"%s" constraint expected an argument of type "%s", got "%s".', self::class, Crawler::class, get_debug_type($other)));
        }

        $other = $other->filter($this->selector);
        if (!\count($other)) {
            $this->hasNode = false;

            return false;
        }

        $this->hasNode = true;

        $nodes = $other->each(fn (Crawler $node) => $node->text(null, true));
        $matches = array_filter($nodes, function (string $node): bool {
            return str_contains($node, $this->expectedText);
        });

        return 0 < \count($matches);
    }

    protected function failureDescription($other): string
    {
        if (!$other instanceof Crawler) {
            throw new \InvalidArgumentException(\sprintf('"%s" constraint expected an argument of type "%s", got "%s".', self::class, Crawler::class, get_debug_type($other)));
        }

        return $this->toString();
    }
}
