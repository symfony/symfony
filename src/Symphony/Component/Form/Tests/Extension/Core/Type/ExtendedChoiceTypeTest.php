<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Core\Type;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\Forms;
use Symphony\Component\Form\Tests\Fixtures\ChoiceTypeExtension;
use Symphony\Component\Form\Tests\Fixtures\LazyChoiceTypeExtension;

class ExtendedChoiceTypeTest extends TestCase
{
    /**
     * @dataProvider provideTestedTypes
     */
    public function testChoicesAreOverridden($type)
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addTypeExtension(new ChoiceTypeExtension($type))
            ->getFormFactory()
        ;

        $choices = $factory->create($type, null, array('choice_loader' => null))->createView()->vars['choices'];

        $this->assertCount(2, $choices);
        $this->assertSame('A', $choices[0]->label);
        $this->assertSame('a', $choices[0]->value);
        $this->assertSame('B', $choices[1]->label);
        $this->assertSame('b', $choices[1]->value);
    }

    /**
     * @dataProvider provideTestedTypes
     */
    public function testChoiceLoaderIsOverridden($type)
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addTypeExtension(new LazyChoiceTypeExtension($type))
            ->getFormFactory()
        ;

        $choices = $factory->create($type)->createView()->vars['choices'];

        $this->assertCount(2, $choices);
        $this->assertSame('Lazy A', $choices[0]->label);
        $this->assertSame('lazy_a', $choices[0]->value);
        $this->assertSame('Lazy B', $choices[1]->label);
        $this->assertSame('lazy_b', $choices[1]->value);
    }

    public function provideTestedTypes()
    {
        yield array(CountryTypeTest::TESTED_TYPE);
        yield array(CurrencyTypeTest::TESTED_TYPE);
        yield array(LanguageTypeTest::TESTED_TYPE);
        yield array(LocaleTypeTest::TESTED_TYPE);
        yield array(TimezoneTypeTest::TESTED_TYPE);
    }
}
