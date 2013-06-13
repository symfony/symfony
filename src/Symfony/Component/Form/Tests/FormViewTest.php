<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\FormView;

class FormViewTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider isValidProvider
     */
    public function testIsValid(FormView $view, $valid)
    {
        $this->assertEquals($valid, $view->isValid());
    }

    public function isValidProvider()
    {
        $valid_form = new FormView();
        $invalid_form = new FormView();
        $invalid_form->vars = array(
            'errors' => array('a' => 'a')
        );

        $childless_valid_form = new FormView();
        $childless_valid_form->vars = array('errors' => array());

        $populated_valid_form = new FormView();
        $populated_valid_form->children = array(
            'a' => $valid_form
        );

        $populated_invalid_form = new FormView();
        $populated_invalid_form->children = array(
            'a' => $invalid_form
        );

        $grandpa_populated_valid_form = new FormView();
        $grandpa_populated_valid_form->children = array(
            'a' => $populated_valid_form
        );

        $grandpa_populated_invalid_form = new FormView();
        $grandpa_populated_invalid_form->children = array(
            'a' => $populated_invalid_form
        );

        $grandpa_populated_invalid_form2 = new FormView();
        $grandpa_populated_invalid_form2->children = array(
            'a' => $valid_form,
            'b' => $populated_invalid_form
        );

        return array(
            array($valid_form, true),
            array($invalid_form, false),
            array($childless_valid_form, true),
            array($populated_valid_form, true),
            array($populated_invalid_form, false),
            array($grandpa_populated_valid_form, true),
            array($grandpa_populated_invalid_form, false),
            array($grandpa_populated_invalid_form2, false),
        );
    }


}
