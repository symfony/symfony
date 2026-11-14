<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Compatibility;

/**
 * Describes the HTML or CSS features a registry supports.
 *
 * @experimental
 */
interface SupportProviderInterface
{
    /**
     * @return array<string, Support> Keyed by feature, like "css.properties.color"
     */
    public function getSupport(): array;
}
