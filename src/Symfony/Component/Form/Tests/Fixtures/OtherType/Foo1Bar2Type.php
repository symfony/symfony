<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\OtherType;

use Symfony\Component\Form\AbstractType;

class Foo1Bar2Type extends AbstractType
{
    public function getParent()
    {
        return 'Symfony\Component\Form\Tests\Fixtures\Foo1Bar2Type';
    }
}
