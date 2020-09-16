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

use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Foo;

class AttributeController
{
    public function action(#[Foo('bar')] string $baz) {
    }

    public function invalidAction(#[Foo('bar'), Foo('bar')] string $baz) {
    }
}
