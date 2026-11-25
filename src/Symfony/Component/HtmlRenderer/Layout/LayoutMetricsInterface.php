<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Layout;

use Symfony\Component\HtmlRenderer\Font\FontStyle;

/**
 * Device constraints applied to used lengths while formatting a document.
 *
 * Measurements must remain stable for the duration of one layout computation.
 *
 * @experimental
 */
interface LayoutMetricsInterface
{
    public function resolveUnit(float $number, string $unit, bool $vertical, float $resolved): float;

    /** @param string $property A CSS length or formatting purpose, such as inline-border-top-width or percentage-inset (after expression evaluation) */
    public function constrainLength(float $length, string $property, bool $vertical, ?FontStyle $font = null): float;

    /** Additional finite, non-negative inline-start padding reserved for an outside marker group. */
    public function outsideMarkerGutter(float $naturalGroupWidth): float;

    /** Finite, non-negative thickness in document units; zero leaves the scrollport unobstructed. */
    public function scrollbarThickness(bool $vertical, bool $thin = false): float;

    /**
     * @param list<float> $lengths
     *
     * @return list<float>
     */
    public function distribute(array $lengths, bool $vertical): array;
}
