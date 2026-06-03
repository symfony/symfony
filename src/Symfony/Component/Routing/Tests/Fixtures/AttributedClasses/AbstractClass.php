<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\AttributedClasses;

use Symfony\Component\Routing\Attribute\Route;

abstract class AbstractClass
{
    abstract public function abstractRouteAction();

    #[Route('/path/to/route/{arg1}')]
    public function routeAction($arg1, $arg2 = 'defaultValue2', $arg3 = 'defaultValue3')
    {
    }
}
