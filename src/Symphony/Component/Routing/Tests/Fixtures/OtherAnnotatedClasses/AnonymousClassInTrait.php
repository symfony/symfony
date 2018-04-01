<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Tests\Fixtures\OtherAnnotatedClasses;

trait AnonymousClassInTrait
{
    public function test()
    {
        return new class() {
            public function foo()
            {
            }
        };
    }
}
