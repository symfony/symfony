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


class CountryTypeTest extends LocalizedTestCase
{
    public function testCountriesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $form = $this->factory->create('country');
        $view = $form->createView();
        $choices = $view->get('choices');
        $labels = $view->get('choice_labels');

        $this->assertContains('DE', $choices);
        $this->assertEquals('Deutschland', $labels['DE']);
        $this->assertContains('GB', $choices);
        $this->assertEquals('Vereinigtes Königreich', $labels['GB']);
        $this->assertContains('US', $choices);
        $this->assertEquals('Vereinigte Staaten', $labels['US']);
        $this->assertContains('FR', $choices);
        $this->assertEquals('Frankreich', $labels['FR']);
        $this->assertContains('MY', $choices);
        $this->assertEquals('Malaysia', $labels['MY']);
    }

    public function testUnknownCountryIsNotIncluded()
    {
        $form = $this->factory->create('country', 'country');
        $view = $form->createView();
        $choices = $view->get('choices');

        $this->assertArrayNotHasKey('ZZ', $choices);
    }
}
