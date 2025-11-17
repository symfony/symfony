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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Core\Type\CurrencyType;
use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

class CurrencyTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = CurrencyType::class;

    protected function setUp(): void
    {
        IntlTestHelper::requireIntl($this);

        parent::setUp();
    }

    public function testCurrenciesAreSelectable()
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('EUR', 'EUR', 'Euro'), $choices);
        $this->assertContainsEquals(new ChoiceView('USD', 'USD', 'US Dollar'), $choices);
    }

    #[RequiresPhpExtension('intl')]
    public function testChoiceTranslationLocaleOption()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'choice_translation_locale' => 'uk',
            ])
            ->createView()->vars['choices'];

        // Don't check objects for identity
        $this->assertContainsEquals(new ChoiceView('EUR', 'EUR', 'євро'), $choices);
        $this->assertContainsEquals(new ChoiceView('USD', 'USD', 'долар США'), $choices);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'EUR', $expectedData = 'EUR')
    {
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }

    #[RequiresPhpExtension('intl')]
    public function testAnActiveAndLegalTenderCurrencyIn2006()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'choice_translation_locale' => 'fr',
                'active_at' => new \DateTimeImmutable('2006-01-01', new \DateTimeZone('Etc/UTC')),
                'legal_tender' => true,
            ])
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('SIT', 'SIT', 'tolar slovène'), $choices);
    }

    #[RequiresPhpExtension('intl')]
    public function testAnExpiredCurrencyIn2007()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'choice_translation_locale' => 'fr',
                'legal_tender' => true,
                // The SIT currency expired on 2007-01-14.
                'active_at' => new \DateTimeImmutable('2007-01-15', new \DateTimeZone('Etc/UTC')),
            ])
            ->createView()->vars['choices'];

        $this->assertNotContainsEquals(new ChoiceView('SIT', 'SIT', 'tolar slovène'), $choices);
    }

    #[RequiresPhpExtension('intl')]
    public function testRetrieveExpiredCurrenciesIn2007()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'choice_translation_locale' => 'fr',
                'legal_tender' => true,
                'active_at' => null,
                // The SIT currency expired on 2007-01-14.
                'not_active_at' => new \DateTimeImmutable('2007-01-15', new \DateTimeZone('Etc/UTC')),
            ])
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('SIT', 'SIT', 'tolar slovène'), $choices);
    }

    public function testAnExceptionShouldBeThrownWhenTheActiveAtAndNotActiveAtOptionsAreBothSet()
    {
        $this->expectException(InvalidOptionsException::class);

        $this->expectExceptionMessage('The "active_at" and "not_active_at" options cannot be used together.');

        $this->factory
            ->create(static::TESTED_TYPE, null, [
                'active_at' => new \DateTimeImmutable(),
                'not_active_at' => new \DateTimeImmutable(),
            ])
            ->createView();
    }
}
