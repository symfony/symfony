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
        $choices = $view->getVar('choices');

        // Don't check objects for identity
        $this->assertContains(new ChoiceView('DE', 'Deutschland'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('GB', 'Vereinigtes Königreich'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('US', 'Vereinigte Staaten'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('FR', 'Frankreich'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('MY', 'Malaysia'), $choices, '', false, false);
    }

    public function testUnknownCountryIsNotIncluded()
    {
        $form = $this->factory->create('country', 'country');
        $view = $form->createView();
        $choices = $view->getVar('choices');

        foreach ($choices as $choice) {
            if ('ZZ' === $choice->getValue()) {
                $this->fail('Should not contain choice "ZZ"');
            }
        }
    }
}
