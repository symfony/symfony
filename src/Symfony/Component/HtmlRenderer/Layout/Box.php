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
final readonly class Box
{
    /**
     * @param array<string, string>                            $style
     * @param list<self>                                       $children
     * @param array<string, ?\Dom\Element>                     $anchorReferences
     * @param list<array{array<string, string>, list<string>}> $positionTries
     */
    public function __construct(public ?\Dom\Node $node, public string $display, public array $style = [], public array $children = [], public ?string $text = null, public ?ImageResource $image = null, public ?TextSelection $selection = null, public ?string $pseudoElement = null, public ?int $topLayer = null, public ?ImageResource $borderImage = null, public ?\Dom\Element $anchor = null, public ?ReplacedContentInterface $replacedContent = null, public array $anchorReferences = [], public array $positionTries = [])
    {
    }
}
