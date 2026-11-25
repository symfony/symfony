<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Widget;

use Symfony\Bridge\TuiHtml\Internal\ContextBridge;
use Symfony\Bridge\TuiHtml\Render\RenderContext;
use Symfony\Component\Tui\Render\RenderContext as NativeContext;
use Symfony\Component\Tui\Widget\AbstractWidget;

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

    public function render(NativeContext|RenderContext $context): array
    {
        $context = ContextBridge::computed($context);

        return [str_repeat($this->character, max(0, $context->getColumns()))];
    }
}
