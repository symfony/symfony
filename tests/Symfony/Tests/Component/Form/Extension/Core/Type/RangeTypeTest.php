<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\Exception\UnexpectedTypeException;

class RangeTypeTest extends TypeTestCase
{
    private $range = array(1, 3);

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testRangeOptionExpectsArray()
    {
        $form = $this->factory->create('range', null, array(
            'range' => new \ArrayObject,
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testRequiresChoicesOrChoiceListOption()
    {
        $this->factory->create('range', 'name');
    }

    public function testRangeCreatesChoices()
    {
        $form = $this->factory->create('range', null, array(
            'range' => $this->range,
        ));
        
        $view = $form->createView();
        
        $this->assertSame(array(1 => 1, 2 => 2, 3 => 3), $view->get('choices'));
    }

}
