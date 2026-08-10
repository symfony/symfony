<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures\WithAccessors;

use Symfony\Component\PropertyInfo\Attribute\WithAccessors;

class Foo
{
    #[WithAccessors(getter: 'retrieveName', setter: 'renameTo')]
    private string $name;

    public function retrieveName(): string
    {
    }

    public function renameTo(string $name): void
    {
    }
}
