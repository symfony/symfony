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

class TestOverwritePropClass extends FinalPublicClass
{
    public function __construct(
        protected string $dep,
        protected int $count,
    ) {
    }

    public function getDep(): string
    {
        return $this->dep;
    }
}
