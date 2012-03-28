<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

class RadioTypeTest extends TypeTestCase
{
    public function testPassParentFullNameToView()
    {
        $parent = $this->factory->createNamed('field', 'parent');
        $parent->add($this->factory->createNamed('radio', 'child'));
        $view = $parent->createView();

        $this->assertEquals('parent', $view['child']->get('full_name'));
    }
}
