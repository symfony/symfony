<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug;

use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * @internal
 */
final class SpacerWidget extends AbstractWidget
{
    public function __construct(
        private int $rows = 0,
    ) {
    }

    public function setRows(int $rows): void
    {
        $rows = max(0, $rows);

        if ($rows === $this->rows) {
            return;
        }

        $this->rows = $rows;
        $this->invalidate();
    }

    public function render(RenderContext $context): array
    {
        return array_fill(0, $this->rows, '');
    }
}
