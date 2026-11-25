<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Content;

/**
 * Supplies atomic content without changing document semantics or owning a runtime.
 *
 * A provider must remain stable and must not mutate document or application state during a frame.
 * Layout can query it more than once at different available sizes. The containing engine owns
 * CSS sizing, borders, padding, clipping and stacking; all drawing coordinates are local CSS pixels.
 *
 * @experimental
 */
interface ReplacedContentInterface
{
    public function getIntrinsicSize(): IntrinsicSize;

    /** Records content for the allocated width and available content height; it performs no device painting. */
    public function layout(float $width, float $availableHeight): ContentLayout;
}
