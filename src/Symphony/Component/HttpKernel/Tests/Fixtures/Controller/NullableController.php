<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\Fixtures\Controller;

class NullableController
{
    public function action(?string $foo, ?\stdClass $bar, ?string $baz = 'value', $mandatory)
    {
    }
}
