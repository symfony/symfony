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
    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testStartOptionIsRequired()
    {
        $form = $this->factory->create('range', null, array(
            'start' => null,
            'end'   => 3,
            'step'  => 1,
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testEndOptionIsRequired()
    {
        $form = $this->factory->create('range', null, array(
            'start' => 1,
            'end'   => null,
            'step'  => 1,
        ));
    }

    public function testStepOptionIsOptional()
    {
        $form = $this->factory->create('range', null, array(
            'start' => 1,
            'end'   => 3,
        ));
    }

    public function testRangeCreatesChoices()
    {
        $form = $this->factory->create('range', null, array(
            'start' => 1,
            'end'   => 3,
            'step'  => 1,
        ));
        
        $view = $form->createView();
        
        $this->assertSame(array(1 => 1, 2 => 2, 3 => 3), $view->get('choices'));
    }

    public function testRangeAppendsToExistingChoices()
    {
        $form = $this->factory->create('range', null, array(
            'start'     => 1,
            'end'       => 3,
            'step'      => 1,
            'choices'   => array(
                0 => 0,
            )
        ));
        
        $view = $form->createView();
        
        $this->assertSame(array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3
        ), $view->get('choices'));
    }
}
