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

use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Intl\Util\IntlTestHelper;

class CountryTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\CountryType';

    protected function setUp()
    {
        IntlTestHelper::requireIntl($this, false);

        parent::setUp();
    }

    public function testCountriesAreSelectable()
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        // Don't check objects for identity
        $this->assertContains(new ChoiceView('DE', 'DE', 'Germany'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('GB', 'GB', 'United Kingdom'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('US', 'US', 'United States'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('FR', 'FR', 'France'), $choices, '', false, false);
        $this->assertContains(new ChoiceView('MY', 'MY', 'Malaysia'), $choices, '', false, false);
    }

    public function testUnknownCountryIsNotIncluded()
    {
        $choices = $this->factory->create(static::TESTED_TYPE, 'country')
            ->createView()->vars['choices'];

        $countryCodes = array();

        foreach ($choices as $choice) {
            $countryCodes[] = $choice->value;
        }

        $this->assertNotContains('ZZ', $countryCodes);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }
}
