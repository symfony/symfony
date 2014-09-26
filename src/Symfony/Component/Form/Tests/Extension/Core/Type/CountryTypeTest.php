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

use Symfony\Component\Form\Test\TypeTestCase as TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Intl\Util\IntlTestHelper;

class CountryTypeTest extends TestCase
{
    protected function setUp()
    {
        IntlTestHelper::requireIntl($this);

        parent::setUp();
    }

    public function testCountriesAreSelectable()
    {
        $form = $this->factory->create('country');
        $view = $form->createView();
        $choices = $view->vars['choices'];

        // Don't check objects for identity
        $this->assertContains(new ChoiceView('Germany', 'DE', 'DE'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('United Kingdom', 'GB', 'GB'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('United States', 'US', 'US'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('France', 'FR', 'FR'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('Malaysia', 'MY', 'MY'), $choices, '', false, false);
    }

    public function testUnknownCountryIsNotIncluded()
    {
        $form = $this->factory->create('country', 'country');
        $view = $form->createView();
        $choices = $view->vars['choices'];

        foreach ($choices as $choice) {
            if ('ZZ' === $choice->value) {
                $this->fail('Should not contain choice "ZZ"');
            }
        }
    }
}
