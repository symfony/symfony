<?php

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\LocaleField;
use Symfony\Component\Form\FormConfiguration;

class LocaleFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testLocalesAreSelectable()
    {
        FormConfiguration::setDefaultLocale('de_AT');

        $field = new LocaleField('language');
        $choices = $field->getOtherChoices();

        $this->assertArrayHasKey('en', $choices);
        $this->assertEquals('Englisch', $choices['en']);
        $this->assertArrayHasKey('en_GB', $choices);
        $this->assertEquals('Englisch (Vereinigtes Königreich)', $choices['en_GB']);
        $this->assertArrayHasKey('zh_Hans_MO', $choices);
        $this->assertEquals('Chinesisch (vereinfacht, Sonderverwaltungszone Macao)', $choices['zh_Hans_MO']);
    }
}