<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class Php84DummyWithMagicGet
{
    public bool $virtualSetOnly { set (bool $value) {} }
    private bool $privateVirtualSetOnly { set (bool $value) {} }

    public function __get(string $name): mixed
    {
        return null;
    }
}
