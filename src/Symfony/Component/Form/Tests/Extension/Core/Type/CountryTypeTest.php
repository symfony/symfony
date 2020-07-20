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

    protected function setUp(): void
    {
        IntlTestHelper::requireIntl($this, false);

        parent::setUp();
    }

    public function testCountriesAreSelectable()
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        // Don't check objects for identity
        $this->assertContainsEquals(new ChoiceView('DE', 'DE', 'Germany'), $choices);
        $this->assertContainsEquals(new ChoiceView('GB', 'GB', 'United Kingdom'), $choices);
        $this->assertContainsEquals(new ChoiceView('US', 'US', 'United States'), $choices);
        $this->assertContainsEquals(new ChoiceView('FR', 'FR', 'France'), $choices);
        $this->assertContainsEquals(new ChoiceView('MY', 'MY', 'Malaysia'), $choices);
    }

    /**
     * @requires extension intl
     */
    public function testChoiceTranslationLocaleOption()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'choice_translation_locale' => 'uk',
            ])
            ->createView()->vars['choices'];

        // Don't check objects for identity
        $this->assertContainsEquals(new ChoiceView('DE', 'DE', 'Німеччина'), $choices);
        $this->assertContainsEquals(new ChoiceView('GB', 'GB', 'Велика Британія'), $choices);
        $this->assertContainsEquals(new ChoiceView('US', 'US', 'Сполучені Штати'), $choices);
        $this->assertContainsEquals(new ChoiceView('FR', 'FR', 'Франція'), $choices);
        $this->assertContainsEquals(new ChoiceView('MY', 'MY', 'Малайзія'), $choices);
    }

    public function testAlpha3Option()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'alpha3' => true,
            ])
            ->createView()->vars['choices'];

        // Don't check objects for identity
        $this->assertContainsEquals(new ChoiceView('DEU', 'DEU', 'Germany'), $choices);
        $this->assertContainsEquals(new ChoiceView('GBR', 'GBR', 'United Kingdom'), $choices);
        $this->assertContainsEquals(new ChoiceView('USA', 'USA', 'United States'), $choices);
        $this->assertContainsEquals(new ChoiceView('FRA', 'FRA', 'France'), $choices);
        $this->assertContainsEquals(new ChoiceView('MYS', 'MYS', 'Malaysia'), $choices);
    }

    /**
     * @requires extension intl
     */
    public function testChoiceTranslationLocaleAndAlpha3Option()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'choice_translation_locale' => 'uk',
                'alpha3' => true,
            ])
            ->createView()->vars['choices'];

        // Don't check objects for identity
        $this->assertContainsEquals(new ChoiceView('DEU', 'DEU', 'Німеччина'), $choices);
        $this->assertContainsEquals(new ChoiceView('GBR', 'GBR', 'Велика Британія'), $choices);
        $this->assertContainsEquals(new ChoiceView('USA', 'USA', 'Сполучені Штати'), $choices);
        $this->assertContainsEquals(new ChoiceView('FRA', 'FRA', 'Франція'), $choices);
        $this->assertContainsEquals(new ChoiceView('MYS', 'MYS', 'Малайзія'), $choices);
    }

    public function testUnknownCountryIsNotIncluded()
    {
        $choices = $this->factory->create(static::TESTED_TYPE, 'country')
            ->createView()->vars['choices'];

        $countryCodes = [];

        foreach ($choices as $choice) {
            $countryCodes[] = $choice->value;
        }

        $this->assertNotContains('ZZ', $countryCodes);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'FR', $expectedData = 'FR')
    {
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }
}
