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
 * A widget that draws decorations around its content, which a style
 * provider can replace: CSS ::before and ::after with content.
 *
 * @experimental
 */
interface GeneratedContentInterface
{
    /**
     * @param string|null $before The decoration before the content, null for the widget default
     * @param string|null $after  The decoration after the content, null for the widget default
     */
    public function setGeneratedContent(?string $before, ?string $after): void;
}
