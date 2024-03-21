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
 * @extends \IteratorAggregate<int, \DOMNode>|\IteratorAggregate<int, \DOM\Node>
 */
interface CrawlerInterface extends \Countable, \IteratorAggregate
{
    public function add(\DOMNodeList|\DOMNode|array|string|null $node): void;

    public function clear(): void;

    public function extract(array $attributes): array;

    public function filter(string $selector): static;

    public function link(string $method = 'get'): Link;

    public function text(?string $default = null, bool $normalizeWhitespace = true): string;

    public function selectButton(string $value): static;

    public function selectLink(string $value): static;
}
