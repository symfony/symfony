<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

use Symfony\Component\Form\AbstractType;

class RecursiveFormTypeObjectBaz extends AbstractType
{
    public function getParent()
    {
        return new RecursiveFormTypeObjectFoo();
    }

    public function getName()
    {
        return 'baz_object_type';
    }
}
