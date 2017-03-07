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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\Tests\Fixtures\ChoiceTypeExtension;

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

        $choices = $factory->create($type)->createView()->vars['choices'];

        $this->assertCount(2, $choices);
        $this->assertSame('A', $choices[0]->label);
        $this->assertSame('a', $choices[0]->value);
        $this->assertSame('B', $choices[1]->label);
        $this->assertSame('b', $choices[1]->value);
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
