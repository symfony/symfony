<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

/** A prepared matching and cascade stage, without computed styles.
 *
 * @internal
 */
final class PreparedCascade
{
    public function __construct(
        public readonly \Dom\Document $document,
        public readonly float $width,
        public readonly float $height,
        public readonly ParsedStylesheet $stylesheet,
        public readonly array $elements,
        public readonly \WeakMap $states,
        public readonly \WeakMap $winners,
        public readonly ?\WeakMap $startingWinners,
        public readonly \WeakMap $startingElements,
        public readonly \WeakMap $matchedRules,
        public readonly array $pseudoWinners,
        public readonly \SplObjectStorage $userAgentWinners,
        public readonly \SplObjectStorage $explicitWinners,
        public readonly ?\SplObjectStorage $identities,
        public readonly ?\SplObjectStorage $styleParents,
    ) {
    }
}
