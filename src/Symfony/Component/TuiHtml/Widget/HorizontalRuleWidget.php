<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Widget;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Internal\ContextBridge;
use Symfony\Component\TuiHtml\Render\RenderContext;

/**
 * A line across the available width.
 *
 * @experimental
 */
class HorizontalRuleWidget extends AbstractWidget
{
    public function __construct(private readonly string $character = '─')
    {
    }

    public function render(\Symfony\Component\Tui\Render\RenderContext|RenderContext $context): array
    {
        $context = ContextBridge::computed($context);

        return [str_repeat($this->character, max(0, $context->getColumns()))];
    }
}
