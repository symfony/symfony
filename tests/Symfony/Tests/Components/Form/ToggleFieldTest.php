<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/Fixtures/TestToggleField.php';

use Symfony\Tests\Components\Form\Fixtures\TestToggleField;

class ToggleFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testRender_selected()
    {
        $field = new TestToggleField('name');
        $field->setData(true);

        $html = '<input id="name" name="name" checked="checked" class="foobar" />';

        $this->assertEquals($html, $field->render(array(
            'class' => 'foobar',
        )));
    }

    public function testRender_deselected()
    {
        $field = new TestToggleField('name');
        $field->setData(false);

        $html = '<input id="name" name="name" />';

        $this->assertEquals($html, $field->render());
    }

    public function testRender_withValue()
    {
        $field = new TestToggleField('name', array('value' => 'foobar'));

        $html = '<input id="name" name="name" value="foobar" />';

        $this->assertEquals($html, $field->render());
    }

    public function testRender_withLabel()
    {
        $field = new TestToggleField('name', array('label' => 'foobar'));

        $html = '<input id="name" name="name" /> <label for="name">foobar</label>';

        $this->assertEquals($html, $field->render());
    }

    public function testRender_withTranslatedLabel()
    {
        $translator = $this->getMock('Symfony\Components\I18N\TranslatorInterface');
        $translator->expects($this->any())
                             ->method('translate')
                             ->will($this->returnCallback(function($text) {
                                 return 'translated['.$text.']';
                             }));

        $field = new TestToggleField('name', array('label' => 'foobar', 'translate_label' => true));
        $field->setTranslator($translator);

        $html = '<input id="name" name="name" /> <label for="name">translated[foobar]</label>';

        $this->assertEquals($html, $field->render());
    }
}