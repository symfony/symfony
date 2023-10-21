<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures\Controller;

use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Bar;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Baz;
use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Foo;

#[Bar('class'), Undefined('class')]
class AttributeController
{
    #[Bar('method'), Baz, Undefined('method')]
    public function __invoke()
    {
    }

    public function action(#[Foo('bar')] string $baz)
    {
    }

    public function multiAttributeArg(#[Foo('bar'), Undefined('bar')] string $baz)
    {
    }

    public function issue41478(#[Foo('bar')] string $baz, string $bat)
    {
    }
}
