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
        $labels = $view->get('choice_labels');

        $this->assertContains('en', $choices);
        $this->assertEquals('Englisch', $labels[array_search('en', $choices)]);
        $this->assertContains('en_GB', $choices);
        $this->assertEquals('Britisches Englisch', $labels[array_search('en_GB', $choices)]);
        $this->assertContains('en_US', $choices);
        $this->assertEquals('Amerikanisches Englisch', $labels[array_search('en_US', $choices)]);
        $this->assertContains('fr', $choices);
        $this->assertEquals('Französisch', $labels[array_search('fr', $choices)]);
        $this->assertContains('my', $choices);
        $this->assertEquals('Birmanisch', $labels[array_search('my', $choices)]);
    }

    public function testMultipleLanguagesIsNotIncluded()
    {
        $form = $this->factory->create('language', 'language');
        $view = $form->createView();
        $choices = $view->get('choices');

        $this->assertArrayNotHasKey('mul', $choices);
    }
}
