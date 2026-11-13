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

use Symfony\Component\Tui\Render\RenderContext;

/**
 * A line across the available width.
 *
 * @experimental
 */
class HorizontalRuleWidget extends AbstractWidget
{
    public function __construct(
        private readonly string $character = '─',
    ) {
    }

    public function render(RenderContext $context): array
    {
        return [str_repeat($this->character, max(0, $context->getColumns()))];
    }
}
