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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Type\TimezoneType;
use Symfony\Component\Intl\Util\IntlTestHelper;

class TimezoneTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = TimezoneType::class;

    public function testTimezonesAreSelectable()
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('Africa/Kinshasa', 'Africa/Kinshasa', 'Africa / Kinshasa'), $choices);
        $this->assertContainsEquals(new ChoiceView('America/New_York', 'America/New_York', 'America / New York'), $choices);
    }

    #[IgnoreDeprecations]
    #[Group('legacy')]
    public function testRegionsOptionIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/form 8.2: The "regions" option is deprecated. It has had no effect since 5.0 and will be removed in 9.0.');

        $this->factory->create(static::TESTED_TYPE, null, ['regions' => \DateTimeZone::EUROPE]);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'Africa/Kinshasa', $expectedData = 'Africa/Kinshasa')
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        $this->assertSame($emptyData, $form->getViewData());
        $this->assertSame($expectedData, $form->getNormData());
        $this->assertSame($expectedData, $form->getData());
    }

    public function testDateTimeZoneInput()
    {
        $form = $this->factory->create(static::TESTED_TYPE, new \DateTimeZone('America/New_York'), ['input' => 'datetimezone']);

        $this->assertSame('America/New_York', $form->createView()->vars['value']);

        $form->submit('Europe/Amsterdam');

        $this->assertEquals(new \DateTimeZone('Europe/Amsterdam'), $form->getData());

        $form = $this->factory->create(static::TESTED_TYPE, [new \DateTimeZone('America/New_York')], ['input' => 'datetimezone', 'multiple' => true]);

        $this->assertSame(['America/New_York'], $form->createView()->vars['value']);

        $form->submit(['Europe/Amsterdam', 'Europe/Paris']);

        $this->assertEquals([new \DateTimeZone('Europe/Amsterdam'), new \DateTimeZone('Europe/Paris')], $form->getData());
    }

    public function testDateTimeZoneInputWithBc()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['input' => 'datetimezone']);
        $form->submit('Europe/Saratov');

        $this->assertEquals(new \DateTimeZone('Europe/Saratov'), $form->getData());
        $this->assertContainsEquals('Europe/Saratov', $form->getConfig()->getAttribute('choice_list')->getValues());
    }

    #[RequiresPhpExtension('intl')]
    public function testIntlTimeZoneInput()
    {
        $form = $this->factory->create(static::TESTED_TYPE, \IntlTimeZone::createTimeZone('America/New_York'), ['input' => 'intltimezone']);

        $this->assertSame('America/New_York', $form->createView()->vars['value']);

        $form->submit('Europe/Amsterdam');

        $this->assertEquals(\IntlTimeZone::createTimeZone('Europe/Amsterdam'), $form->getData());

        $form = $this->factory->create(static::TESTED_TYPE, [\IntlTimeZone::createTimeZone('America/New_York')], ['input' => 'intltimezone', 'multiple' => true]);

        $this->assertSame(['America/New_York'], $form->createView()->vars['value']);

        $form->submit(['Europe/Amsterdam', 'Europe/Paris']);

        $this->assertEquals([\IntlTimeZone::createTimeZone('Europe/Amsterdam'), \IntlTimeZone::createTimeZone('Europe/Paris')], $form->getData());
    }

    #[RequiresPhpExtension('intl')]
    public function testIntlTimeZoneInputWithBc()
    {
        $reflector = new \ReflectionExtension('intl');
        ob_start();
        $reflector->info();
        $output = strip_tags(ob_get_clean());
        preg_match('/^ICU TZData version (?:=>)?(.*)$/m', $output, $matches);
        $tzDbVersion = isset($matches[1]) ? (int) trim($matches[1]) : 0;

        if (!$tzDbVersion || 2017 <= $tzDbVersion) {
            $this->markTestSkipped('"Europe/Saratov" is expired until 2017, current version is '.$tzDbVersion);
        }

        $form = $this->factory->create(static::TESTED_TYPE, null, ['input' => 'intltimezone']);
        $form->submit('Europe/Saratov');

        $this->assertNull($form->getData());
        $this->assertNotContains('Europe/Saratov', $form->getConfig()->getAttribute('choice_list')->getValues());
    }

    #[RequiresPhpExtension('intl')]
    public function testIntlTimeZoneInputWithBcAndIntl()
    {
        $reflector = new \ReflectionExtension('intl');
        ob_start();
        $reflector->info();
        $output = strip_tags(ob_get_clean());
        preg_match('/^ICU TZData version (?:=>)?(.*)$/m', $output, $matches);
        $tzDbVersion = isset($matches[1]) ? (int) trim($matches[1]) : 0;

        if (!$tzDbVersion || 2017 <= $tzDbVersion) {
            $this->markTestSkipped('"Europe/Saratov" is expired until 2017, current version is '.$tzDbVersion);
        }

        $form = $this->factory->create(static::TESTED_TYPE, null, ['input' => 'intltimezone', 'intl' => true]);
        $form->submit('Europe/Saratov');

        $this->assertNull($form->getData());
        $this->assertNotContains('Europe/Saratov', $form->getConfig()->getAttribute('choice_list')->getValues());
    }

    public function testTimezonesAreSelectableWithIntl()
    {
        IntlTestHelper::requireIntl($this);

        $choices = $this->factory->create(static::TESTED_TYPE, null, ['intl' => true])
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('Europe/Amsterdam', 'Europe/Amsterdam', 'Central European Time (Amsterdam)'), $choices);
        $this->assertContainsEquals(new ChoiceView('Etc/UTC', 'Etc/UTC', 'Coordinated Universal Time'), $choices);
    }

    /**
     * ICU lists an IANA identifier and its legacy alias under one display name, so the
     * choices must not be keyed by that name: it would drop one of the two at random.
     */
    #[RequiresPhpExtension('intl')]
    public function testIntlTimezonesKeepTheCanonicalIdentifierOfAnAliasPair()
    {
        $choices = $this->factory->create(static::TESTED_TYPE, null, ['intl' => true])
            ->createView()->vars['choices'];

        $values = array_map(static fn (ChoiceView $choice) => $choice->value, $choices);

        $this->assertContains('Asia/Kolkata', $values);
        $this->assertNotContains('Asia/Calcutta', $values);

        $this->assertContains('Pacific/Chuuk', $values);
        $this->assertNotContains('Pacific/Truk', $values);
    }

    #[RequiresPhpExtension('intl')]
    public function testIntlTimezonesAreSubmittableWithTheirCanonicalIdentifier()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['intl' => true]);
        $form->submit('Asia/Kolkata');

        $this->assertSame('Asia/Kolkata', $form->getData());
        $this->assertTrue($form->isValid());
    }

    #[RequiresPhpExtension('intl')]
    public function testChoiceTranslationLocaleOptionWithIntl()
    {
        $choices = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'intl' => true,
                'choice_translation_locale' => 'uk',
            ])
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('Europe/Amsterdam', 'Europe/Amsterdam', 'за центральноєвропейським часом (Амстердам)'), $choices);
        $this->assertContainsEquals(new ChoiceView('Etc/UTC', 'Etc/UTC', 'за всесвітнім координованим часом'), $choices);
    }

    public function testChoiceTranslationLocaleOptionWithoutIntl()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "choice_translation_locale" option can only be used if the "intl" option is set to true.');
        $this->factory->create(static::TESTED_TYPE, null, [
            'choice_translation_locale' => 'uk',
        ]);
    }
}
