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

class RepeatableControllerAttributeAtMethodController
{
    #[RepeatableFooController(bar: 'method1')]
    #[RepeatableFooController(bar: 'method2')]
    public function foo()
    {
    }
}
