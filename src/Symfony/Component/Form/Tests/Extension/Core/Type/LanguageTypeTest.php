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
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Intl\Util\IntlTestHelper;

class LanguageTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\LanguageType';

    protected function setUp(): void
    {
        IntlTestHelper::requireIntl($this, false);

        parent::setUp();
    }

    public function testCountriesAreSelectable()
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('en', 'en', 'English'), $choices);
        $this->assertContainsEquals(new ChoiceView('fr', 'fr', 'French'), $choices);
        $this->assertContainsEquals(new ChoiceView('my', 'my', 'Burmese'), $choices);
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
        $this->assertContainsEquals(new ChoiceView('en', 'en', 'англійська'), $choices);
        $this->assertContainsEquals(new ChoiceView('fr', 'fr', 'французька'), $choices);
        $this->assertContainsEquals(new ChoiceView('my', 'my', 'бірманська'), $choices);
    }

    public function testAlpha3Option()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'alpha3' => true,
            ])
            ->createView()->vars['choices'];

        // Don't check objects for identity
        $this->assertContainsEquals(new ChoiceView('eng', 'eng', 'English'), $choices);
        $this->assertContainsEquals(new ChoiceView('fra', 'fra', 'French'), $choices);
        // Burmese has no three letter language code
        $this->assertNotContainsEquals(new ChoiceView('my', 'my', 'Burmese'), $choices);
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
        $this->assertContainsEquals(new ChoiceView('eng', 'eng', 'англійська'), $choices);
        $this->assertContainsEquals(new ChoiceView('fra', 'fra', 'французька'), $choices);
        // Burmese has no three letter language code
        $this->assertNotContainsEquals(new ChoiceView('my', 'my', 'бірманська'), $choices);
    }

    /**
     * @requires extension intl
     */
    public function testChoiceSelfTranslationOption()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'choice_self_translation' => true,
            ])
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('cs', 'cs', 'čeština'), $choices);
        $this->assertContainsEquals(new ChoiceView('es', 'es', 'español'), $choices);
        $this->assertContainsEquals(new ChoiceView('fr', 'fr', 'français'), $choices);
        $this->assertContainsEquals(new ChoiceView('ta', 'ta', 'தமிழ்'), $choices);
        $this->assertContainsEquals(new ChoiceView('uk', 'uk', 'українська'), $choices);
        $this->assertContainsEquals(new ChoiceView('yi', 'yi', 'ייִדיש'), $choices);
        $this->assertContainsEquals(new ChoiceView('zh', 'zh', '中文'), $choices);
    }

    /**
     * @requires extension intl
     */
    public function testChoiceSelfTranslationAndAlpha3Options()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'alpha3' => true,
                'choice_self_translation' => true,
            ])
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('spa', 'spa', 'español'), $choices, '', false, false);
        $this->assertContainsEquals(new ChoiceView('yid', 'yid', 'ייִדיש'), $choices, '', false, false);
    }

    public function testSelfTranslationNotAllowedWithChoiceTranslation()
    {
        $this->expectException(LogicException::class);

        $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_locale' => 'es',
            'choice_self_translation' => true,
        ]);
    }

    public function testMultipleLanguagesIsNotIncluded()
    {
        $choices = $this->factory->create(static::TESTED_TYPE, 'language')
            ->createView()->vars['choices'];

        $this->assertNotContainsEquals(new ChoiceView('mul', 'mul', 'Mehrsprachig'), $choices);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'en', $expectedData = 'en')
    {
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }
}
