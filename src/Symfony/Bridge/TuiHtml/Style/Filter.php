<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Style;

/**
 * Terminal compatibility facade for the shared CSS color-filter calculations.
 *
 * @experimental
 */
final class Filter
{
    public const FUNCTIONS = \Symfony\Component\HtmlRenderer\Paint\Filter::FUNCTIONS;

    private readonly \Symfony\Component\HtmlRenderer\Paint\Filter $filter;

    /** @param list<array{string, float}> $functions */
    public function __construct(public readonly array $functions)
    {
        $this->filter = new \Symfony\Component\HtmlRenderer\Paint\Filter($functions);
    }

    public function apply(array $rgb, array $behind = [0, 0, 0]): array
    {
        return $this->filter->apply($rgb, $behind);
    }
}
