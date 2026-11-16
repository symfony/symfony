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

use Symfony\Component\Tui\Widget\AbstractWidget;

/** @internal */
final class FloatLayoutState
{
    private ?array $parent = null;

    public static function mark(AbstractWidget $widget): void
    {
        $parent = $widget->getParent();
        $widget->getExtensionState(self::class)->parent = null === $parent ? null : [\WeakReference::create($parent), $parent->getRenderRevision()];
    }

    public static function isCurrent(AbstractWidget $widget): bool
    {
        $parent = $widget->getExtensionState(self::class)->parent;

        return null !== $parent && null !== ($owner = $parent[0]->get()) && $owner === $widget->getParent() && $parent[1] === $owner->getRenderRevision();
    }
}
