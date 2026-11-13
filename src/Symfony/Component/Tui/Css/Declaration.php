<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

/**
 * @experimental
 */
final class Declaration
{
    public function __construct(
        public readonly string $property,
        public readonly string $value,
        public readonly bool $important = false,
    ) {
    }
}
