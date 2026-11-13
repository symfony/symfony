<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

/**
 * A style rule with a single complex selector.
 *
 * @experimental
 */
final class CssRule
{
    /** The layer key of unlayered rules: after every layer; the prefix keeps keys from comparing as numbers */
    public const UNLAYERED = 'L9999';

    /**
     * @param list<Declaration>    $declarations
     * @param list<MediaQuery>     $media        All must match for the rule to apply
     * @param string               $layer        Sorts like the cascade layers, see CssParser
     * @param bool                 $userAgent    Whether the rule comes from the user agent stylesheet
     * @param list<ContainerQuery> $containers   All must match, each on the nearest container it names
     * @param bool                 $scoped       Whether it is in a @scope: at equal specificity, it wins over a rule that is not, like scope proximity
     * @param bool                 $starting     Whether it is in a @starting-style: it only gives the values transitions start from when an element is first shown
     * @param int                  $context      How deep in shadow trees its style sheet is, 0 for the document: an outer context wins over an inner one, an inner one when important
     */
    public function __construct(
        public readonly Selector $selector,
        public readonly array $declarations,
        public readonly array $media = [],
        public readonly int $order = 0,
        public readonly string $layer = self::UNLAYERED,
        public readonly bool $userAgent = false,
        public readonly array $containers = [],
        public readonly bool $scoped = false,
        public readonly bool $starting = false,
        public readonly int $context = 0,
    ) {
    }
}
