<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class CountryTypeTest extends LocalizedTestCase
{
    public function testCountriesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $form = $this->factory->create('country');
        $view = $form->createView();
        $choices = $view->get('choices');

        $this->assertEquals(new ChoiceView('DE', 'Deutschland'), $choices['DE']);
        $this->assertEquals(new ChoiceView('GB', 'Vereinigtes Königreich'), $choices['GB']);
        $this->assertEquals(new ChoiceView('US', 'Vereinigte Staaten'), $choices['US']);
        $this->assertEquals(new ChoiceView('FR', 'Frankreich'), $choices['FR']);
        $this->assertEquals(new ChoiceView('MY', 'Malaysia'), $choices['MY']);
    }

    public function testUnknownCountryIsNotIncluded()
    {
        $form = $this->factory->create('country', 'country');
        $view = $form->createView();
        $choices = $view->get('choices');

        $this->assertArrayNotHasKey('ZZ', $choices);
    }
}
