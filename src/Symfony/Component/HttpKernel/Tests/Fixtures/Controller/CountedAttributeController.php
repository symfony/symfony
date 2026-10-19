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

use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\Counted;

#[Counted('class')]
class CountedAttributeController
{
    #[Counted('method')]
    public function action(#[Counted('argument')] \stdClass $foo = new \stdClass())
    {
    }

    #[Counted('method')]
    public function __invoke(#[Counted('argument')] \stdClass $foo = new \stdClass())
    {
    }
}
