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

class RecursiveFormTypeStringBar extends AbstractType
{
    public function getParent()
    {
        return 'baz_string_type';
    }

    public function getName()
    {
        return 'bar_string_type';
    }
}
