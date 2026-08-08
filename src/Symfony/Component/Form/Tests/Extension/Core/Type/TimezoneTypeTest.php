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

class TimezoneTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\TimezoneType';

    public function testTimezonesAreSelectable()
    {
        $choices = $this->factory->create(static::TESTED_TYPE)
            ->createView()->vars['choices'];

        $this->assertContainsEquals(new ChoiceView('Africa/Kinshasa', 'Africa/Kinshasa', 'Africa / Kinshasa'), $choices);
        $this->assertContainsEquals(new ChoiceView('America/New_York', 'America/New_York', 'America / New York'), $choices);
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

    /**
     * @requires extension intl
     */
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

    /**
     * @requires extension intl
     */
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

    /**
     * @requires extension intl
     */
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
     * @dataProvider provideEquivalentIdentifiers
     */
    public function testEquivalentIdentifiersAreSubmittable(bool $intl, string $timezone)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['intl' => $intl]);
        $form->submit($timezone);

        $this->assertTrue($form->isValid());
        $this->assertSame($timezone, $form->getData());
    }

    public static function provideEquivalentIdentifiers()
    {
        yield 'canonical identifier sharing an ICU name with its alias' => [true, 'Asia/Kolkata'];
        yield 'identifier absent from the ICU data' => [true, 'UTC'];

        if (null !== $legacy = self::findLegacyIdentifier()) {
            yield 'legacy alias, intl' => [true, $legacy];
            yield 'legacy alias, php' => [false, $legacy];
        }
    }

    /**
     * @dataProvider provideIntlOption
     */
    public function testUnknownIdentifierIsNotSubmittable(bool $intl)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['intl' => $intl]);
        $form->submit('Not/AZone');

        $this->assertFalse($form->isValid());
        $this->assertNull($form->getData());
    }

    /**
     * @dataProvider provideIntlOption
     */
    public function testEquivalentIdentifiersAreNotOffered(bool $intl)
    {
        if (null === $legacy = self::findLegacyIdentifier()) {
            $this->markTestSkipped('The timezone database carries no backward compatibility identifier.');
        }

        $values = $this->factory->create(static::TESTED_TYPE, null, ['intl' => $intl])
            ->getConfig()->getAttribute('choice_list')->getValues();

        $this->assertNotContains($legacy, $values);
        $this->assertContains('Europe/Paris', $values);
    }

    public static function provideIntlOption()
    {
        yield 'intl' => [true];
        yield 'php' => [false];
    }

    private static function findLegacyIdentifier(): ?string
    {
        $legacy = array_diff(
            \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC),
            \DateTimeZone::listIdentifiers(\DateTimeZone::ALL)
        );

        foreach ($legacy as $timezone) {
            if (str_contains($timezone, '/') && !str_starts_with($timezone, 'Etc/')) {
                return $timezone;
            }
        }

        return null;
    }

    /**
     * @requires extension intl
     */
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
