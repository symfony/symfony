<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class ResolvePostValue
{
    public function __construct(
        public readonly string|null $name = null,
        public readonly mixed $default = null,
    ) {
    }
}
