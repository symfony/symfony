<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

use Symfony\Component\HtmlRenderer\Content\ReplacedContentInterface;
use Symfony\Component\HtmlRenderer\Resource\ImageResource;
use Symfony\Component\HtmlRenderer\Text\TextSelection;

/**
 * @internal
 */
final class Box
{
    /**
     * @param array<string, string>                            $style
     * @param list<self>                                       $children
     * @param array<string, ?\Dom\Element>                     $anchorReferences
     * @param list<array{array<string, string>, list<string>}> $positionTries
     */
    public function __construct(public readonly ?\Dom\Node $node, public readonly string $display, public readonly array $style = [], public readonly array $children = [], public readonly ?string $text = null, public readonly ?ImageResource $image = null, public readonly ?TextSelection $selection = null, public readonly ?string $pseudoElement = null, public readonly ?int $topLayer = null, public readonly ?ImageResource $borderImage = null, public readonly ?\Dom\Element $anchor = null, public readonly ?ReplacedContentInterface $replacedContent = null, public readonly array $anchorReferences = [], public readonly array $positionTries = [])
    {
    }
}
