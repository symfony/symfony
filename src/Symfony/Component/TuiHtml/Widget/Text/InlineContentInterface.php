<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget\Text;

/**
 * A widget whose content is inline nodes formatted from the outside.
 *
 * @experimental
 */
interface InlineContentInterface
{
    /**
     * @return list<InlineNode>
     */
    public function getInlineNodes(): array;

    /**
     * @param InlineFormat             $block The format of the block as a whole
     * @param array<int, InlineFormat> $nodes The format of the element nodes, by position in a depth-first walk
     */
    public function setInlineFormats(InlineFormat $block, array $nodes): void;
}
