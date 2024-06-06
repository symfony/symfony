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
class Bar
{
    public const key = 'bar-const';
    public static $key = 'bar-prop';

    public static function key()
    {
        return 'bar-method';
    }
}
