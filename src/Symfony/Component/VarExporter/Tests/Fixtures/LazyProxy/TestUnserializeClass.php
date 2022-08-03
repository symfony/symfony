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

class TestUnserializeClass extends TestClass
{
    public function __serialize(): array
    {
        return [$this->dep];
    }

    public function __unserialize(array $data): void
    {
        $this->dep = $data[0];
        $this->dep->wokeUp = true;
    }
}
