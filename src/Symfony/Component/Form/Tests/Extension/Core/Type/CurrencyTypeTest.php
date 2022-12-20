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

class CurrencyTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\CurrencyType';

    protected function setUp(): void
    {
        IntlTestHelper::requireIntl($this, false);

        parent::setUp();
    }

    public function testCurrenciesAreSelectable()
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        self::assertContainsEquals(new ChoiceView('EUR', 'EUR', 'Euro'), $choices);
        self::assertContainsEquals(new ChoiceView('USD', 'USD', 'US Dollar'), $choices);
        self::assertContainsEquals(new ChoiceView('SIT', 'SIT', 'Slovenian Tolar'), $choices);
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
        self::assertContainsEquals(new ChoiceView('EUR', 'EUR', 'євро'), $choices);
        self::assertContainsEquals(new ChoiceView('USD', 'USD', 'долар США'), $choices);
        self::assertContainsEquals(new ChoiceView('SIT', 'SIT', 'словенський толар'), $choices);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'EUR', $expectedData = 'EUR')
    {
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }
}
