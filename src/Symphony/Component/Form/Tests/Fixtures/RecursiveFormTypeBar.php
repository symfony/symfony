<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Fixtures;

use Symphony\Component\Form\AbstractType;

class RecursiveFormTypeBar extends AbstractType
{
    public function getParent()
    {
        return RecursiveFormTypeBaz::class;
    }
}
