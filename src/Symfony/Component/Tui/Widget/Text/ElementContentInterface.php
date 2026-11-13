<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Text;

/**
 * A widget that draws sub-elements whose content a style provider can replace,
 * like the CSS content of ::picker-icon and ::checkmark.
 *
 * @experimental
 */
interface ElementContentInterface
{
    /**
     * @return list<string> The sub-elements whose content can be replaced
     */
    public function getContentElements(): array;

    /**
     * @param string|null $content The content of the sub-element, '' for none, null for the widget default
     */
    public function setElementContent(string $element, ?string $content): void;
}
