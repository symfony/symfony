<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;

/**
 * Temporary placeholder for debug sections whose shell/menu entry exists before
 * their data providers are implemented.
 *
 * @internal
 *
 * @experimental
 */
final class PlaceholderDebugSection extends AbstractDebugSection
{
    public function __construct(
        private readonly string $label,
        private readonly string $shortLabel,
        private readonly string $summary,
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getShortLabel(): string
    {
        return $this->shortLabel;
    }

    public function describe(DebugItem $item, int $width): string
    {
        return \sprintf(
            "The %s section is reserved for the unified debug command.\n\nIt will list %s once its data provider is implemented.",
            $this->label,
            lcfirst($this->summary),
        );
    }

    protected function buildItems(): array
    {
        return [
            new DebugItem('section', $this->label, $this->summary),
        ];
    }
}
