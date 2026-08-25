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

class DummyWithNonAsciiStaticAccessor
{
    public static function émail(): string
    {
        return 'static';
    }

    public function __get($name)
    {
        return 'magic';
    }
}
