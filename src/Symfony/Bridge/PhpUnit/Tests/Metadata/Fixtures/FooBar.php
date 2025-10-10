<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\Metadata\Fixtures;

use Symfony\Bridge\PhpUnit\Attribute\DnsSensitive;
use Symfony\Bridge\PhpUnit\Attribute\TimeSensitive;

#[DnsSensitive('App\Foo\Bar\A')]
#[DnsSensitive('App\Foo\Bar\B')]
#[TimeSensitive('App\Foo\Bar\A')]
final class FooBar
{
    #[DnsSensitive('App\Foo\Baz\C')]
    public function testOne()
    {
    }

    #[TimeSensitive('App\Foo\Qux\D')]
    #[TimeSensitive('App\Foo\Qux\E')]
    public function testTwo()
    {
    }

    #[DnsSensitive('App\Foo\Corge\F')]
    #[TimeSensitive('App\Foo\Corge\G')]
    public function testThree()
    {
    }
}
