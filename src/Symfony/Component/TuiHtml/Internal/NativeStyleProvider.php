<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Internal;

use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Style\StyleProviderInterface;

/** @internal */
final class NativeStyleProvider implements StyleProviderInterface
{
    private int $columns = 0;

    public function __construct(private StyleSheet $styleSheet, private bool $nativeOnly = false)
    {
    }

    public function prepare(AbstractWidget $root, MediaContext $media): void
    {
        $this->columns = $media->columns;
    }

    public function getStyles(AbstractWidget $widget): array
    {
        if ($this->nativeOnly && str_starts_with($widget::class, 'Symfony\Component\TuiHtml\\')) {
            return [];
        }

        return [StyleBridge::computed($this->styleSheet->resolve($widget, $this->columns))];
    }

    public function getElementStyles(AbstractWidget $widget, string $element): array
    {
        if ($this->nativeOnly && str_starts_with($widget::class, 'Symfony\Component\TuiHtml\\')) {
            return [];
        }

        return [StyleBridge::computed($this->styleSheet->resolveElement($widget, $element))];
    }
}
