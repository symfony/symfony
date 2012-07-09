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

class LanguageTypeTest extends LocalizedTestCase
{
    public function testCountriesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $form = $this->factory->create('language');
        $view = $form->createView();
        $choices = $view->get('choices');

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
        $form = $this->factory->create('language', 'language');
        $view = $form->createView();
        $choices = $view->get('choices');

        $this->assertArrayNotHasKey('mul', $choices);
    }
}
