<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\Valid;

use Symfony\Component\DependencyInjection\Tests\Fixtures\ClassMap\AsFoo;

#[AsFoo]
class Baz implements FooInterface
{
    public const key = 'baz-const';
    public static $key = 'baz-prop';

    public function key()
    {
        return 'baz-method';
    }
}
