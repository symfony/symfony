<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy;

class AsymmetricVisibility
{
    public function __construct(
        public private(set) int $foo,
        private readonly int $bar,
    ) {
    }

    public function getBar(): int
    {
        return $this->bar;
    }
}
