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

require_once __DIR__.'/TestCase.php';

use Symfony\Component\Form\LanguageField;
use Symfony\Component\Form\FormContext;

class LanguageFieldTest extends TestCase
{
    public function testCountriesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $field = $this->factory->getInstance('language', 'language');
        $choices = $field->getRenderer()->getVar('choices');

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
        $field = $this->factory->getInstance('language', 'language');
        $choices = $field->getRenderer()->getVar('choices');

        $this->assertArrayNotHasKey('mul', $choices);
    }
}