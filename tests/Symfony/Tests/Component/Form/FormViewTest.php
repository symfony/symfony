<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\FormView;

class FormViewTest extends \PHPUnit_Framework_TestCase
{
    public function testGetDisplayedData()
    {
        $grandson = new FormView();
        $grandson->set('value', 'Little Timmy');

        $granddaughter = new FormView();
        $granddaughter->set('value', 'Sally Jo');

        $parent = new FormView();
        $parent->setChildren(array(
            'grandson'      => $grandson,
            'granddaughter' => $granddaughter,
        ));

        $grandparent = new FormView();
        $grandparent->setChildren(array(
            'parent' => $parent,
        ));

        $expected = array(
            'parent' => array(
                'grandson'      => 'Little Timmy',
                'granddaughter' => 'Sally Jo',
            ),
        );

        $this->assertEquals($expected, $grandparent->getDisplayedData());
    }
}
