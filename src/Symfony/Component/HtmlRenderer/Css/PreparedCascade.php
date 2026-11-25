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
final readonly class PreparedCascade
{
    public function __construct(
        public \Dom\Document $document,
        public float $width,
        public float $height,
        public ParsedStylesheet $stylesheet,
        public array $elements,
        public \WeakMap $states,
        public \WeakMap $winners,
        public ?\WeakMap $startingWinners,
        public \WeakMap $startingElements,
        public \WeakMap $matchedRules,
        public array $pseudoWinners,
        public \WeakMap $userAgentWinners,
        public \WeakMap $explicitWinners,
        public ?\SplObjectStorage $identities,
        public ?\SplObjectStorage $styleParents,
    ) {
    }
}
