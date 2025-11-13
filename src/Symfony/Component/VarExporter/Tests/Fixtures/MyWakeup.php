<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures;

class MyWakeup
{
    public $sub;
    public $bis;
    public $baz;
    public $def = 234;

    public function __sleep(): array
    {
        return ['sub', 'baz'];
    }

    public function __wakeup(): void
    {
        if (123 === $this->sub) {
            $this->bis = 123;
            $this->baz = 123;
        }
    }
}
