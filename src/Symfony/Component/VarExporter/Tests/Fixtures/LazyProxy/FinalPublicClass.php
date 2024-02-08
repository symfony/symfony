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

class FinalPublicClass
{
    private $count = 0;

    final public function increment(): int
    {
        return $this->count += 1;
    }

    public function decrement(): int
    {
        return $this->count -= 1;
    }
}
