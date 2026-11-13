<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget;

use Symfony\Component\Tui\Style\Style;

/**
 * A widget drawing parts of its own that stand for elements, like the <summary> of a <details>.
 *
 * A style provider matches them as elements, children of the widget's one,
 * and tells the widget their style and the content of their ::marker.
 *
 * @experimental
 */
interface PartsInterface
{
    /**
     * @return array<string, array{string, array<string, string>}> Each part by name: its tag and its attributes
     */
    public function getParts(): array;

    /**
     * @param string|null $marker The content of its ::marker: null for the widget's own, '' for none
     */
    public function setPartStyle(string $part, ?Style $style, ?string $marker): void;
}
