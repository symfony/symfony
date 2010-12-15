<?php

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\LanguageField;
use Symfony\Component\Form\FormConfiguration;

class LanguageFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testCountriesAreSelectable()
    {
        FormConfiguration::setDefaultLocale('de_AT');

        $field = new LanguageField('language');
        $choices = $field->getOtherChoices();

        $this->assertArrayHasKey('en', $choices);
        $this->assertEquals('Englisch', $choices['en']);
        $this->assertArrayHasKey('en_GB', $choices);
        $this->assertEquals('Britisches Englisch', $choices['en_GB']);
        $this->assertArrayHasKey('en_US', $choices);
        $this->assertEquals('Amerikanisches Englisch', $choices['en_US']);
        $this->assertArrayHasKey('fr', $choices);
        $this->assertEquals('Französisch', $choices['fr']);
        $this->assertArrayHasKey('my', $choices);
        $this->assertEquals('Birmanisch', $choices['my']);
    }

    public function testMultipleLanguagesIsNotIncluded()
    {
        $field = new LanguageField('language');
        $choices = $field->getOtherChoices();

        $this->assertArrayNotHasKey('mul', $choices);
    }
}