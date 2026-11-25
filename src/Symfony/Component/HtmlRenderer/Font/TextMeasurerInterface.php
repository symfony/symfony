<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Font;

interface TextMeasurerInterface
{
    /**
     * Measures resolved typography, including spacing, with the provider's shaping policy.
     * Measurements of the same text and font must remain stable during one layout computation.
     */
    public function measure(string $text, FontStyle $font): TextMetrics;
}
