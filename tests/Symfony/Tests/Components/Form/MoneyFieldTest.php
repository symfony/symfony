<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/LocalizedTestCase.php';

use Symfony\Components\Form\MoneyField;

class MoneyFieldTest extends LocalizedTestCase
{
    public function testRenderWithoutCurrency()
    {
        $field = new MoneyField('name');

        $field->setLocale('de_AT');
        $field->setData(1234);

        $html = '<input id="name" name="name" value="1234,00" type="text" class="foobar" />';

        $this->assertEquals($html, $field->render(array('class' => 'foobar')));
    }

    public function testRenderWithCurrency_afterWidget()
    {
        $field = new MoneyField('name', array('currency' => 'EUR'));

        $field->setLocale('de_DE');
        $field->setData(1234);

        $html = '<input id="name" name="name" value="1234,00" type="text" /> €';

        $this->assertEquals($html, $field->render());
    }

    public function testRenderWithCurrency_beforeWidget()
    {
        $field = new MoneyField('name', array('currency' => 'EUR'));

        $field->setLocale('en_US');
        $field->setData(1234);

        $html = '€ <input id="name" name="name" value="1234.00" type="text" />';

        $this->assertEquals($html, $field->render());
    }
}