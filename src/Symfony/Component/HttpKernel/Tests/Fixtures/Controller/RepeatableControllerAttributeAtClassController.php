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

use Symfony\Component\HttpKernel\Tests\Fixtures\Attribute\RepeatableFooController;

#[RepeatableFooController(bar: 'class1')]
#[RepeatableFooController(bar: 'class2')]
class RepeatableControllerAttributeAtClassController
{
    public function foo()
    {
    }
}
