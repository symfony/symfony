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

class FooReadonly
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {
    }
}
