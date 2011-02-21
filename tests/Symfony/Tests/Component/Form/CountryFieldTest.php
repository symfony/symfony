<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\CountryField;
use Symfony\Component\Form\FormContext;

require_once __DIR__.'/TestCase.php';

class CountryFieldTest extends TestCase
{
    public function testCountriesAreSelectable()
    {
        \Locale::setDefault('de_AT');

        $field = $this->factory->getCountryField('country');
        $choices = $field->getRenderer()->getVar('choices');

        $this->assertArrayHasKey('DE', $choices);
        $this->assertEquals('Deutschland', $choices['DE']);
        $this->assertArrayHasKey('GB', $choices);
        $this->assertEquals('Vereinigtes Königreich', $choices['GB']);
        $this->assertArrayHasKey('US', $choices);
        $this->assertEquals('Vereinigte Staaten', $choices['US']);
        $this->assertArrayHasKey('FR', $choices);
        $this->assertEquals('Frankreich', $choices['FR']);
        $this->assertArrayHasKey('MY', $choices);
        $this->assertEquals('Malaysia', $choices['MY']);
    }

    public function testUnknownCountryIsNotIncluded()
    {
        $field = $this->factory->getCountryField('country');
        $choices = $field->getRenderer()->getVar('choices');

        $this->assertArrayNotHasKey('ZZ', $choices);
    }
}