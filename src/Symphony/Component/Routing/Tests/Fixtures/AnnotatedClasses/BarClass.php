<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Tests\Fixtures\AnnotatedClasses;

class BarClass
{
    public function routeAction($arg1, $arg2 = 'defaultValue2', $arg3 = 'defaultValue3')
    {
    }
}
