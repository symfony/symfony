<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Focus;

use Symfony\Component\Tui\Widget\AbstractWidget;

/** @experimental */
interface FocusPolicyInterface
{
    /** Controls focus eligibility, including automatic replacement of hidden focus. */
    public function allowsFocus(AbstractWidget $widget, bool $automatic = false): bool;

    /**
     * Returns a comparison key within the container, or [] when it defines no ordering.
     *
     * @return list<int|float>
     */
    public function getReadingOrder(AbstractWidget $container, AbstractWidget $widget): array;
}
