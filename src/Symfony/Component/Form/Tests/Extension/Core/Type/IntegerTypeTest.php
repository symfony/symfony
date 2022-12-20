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

use Symfony\Component\Intl\Util\IntlTestHelper;

class IntegerTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\IntegerType';

    private $previousLocale;

    protected function setUp(): void
    {
        IntlTestHelper::requireIntl($this, false);
        $this->previousLocale = \Locale::getDefault();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        \Locale::setDefault($this->previousLocale);
    }

    /**
     * @requires extension intl
     */
    public function testArabicLocale()
    {
        \Locale::setDefault('ar');

        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit('123456');

        self::assertSame(123456, $form->getData());
        self::assertSame('123456', $form->getViewData());
    }

    /**
     * @requires extension intl
     */
    public function testArabicLocaleNonHtml5()
    {
        \Locale::setDefault('ar');

        $form = $this->factory->create(static::TESTED_TYPE, null, ['grouping' => true]);
        $form->submit('123456');

        self::assertSame(123456, $form->getData());
        self::assertSame('١٢٣٬٤٥٦', $form->getViewData());
    }

    public function testSubmitRejectsFloats()
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $form->submit('1.678');

        self::assertTrue($form->isSubmitted());
        self::assertFalse($form->isValid());
        self::assertFalse($form->isSynchronized());
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = '10', $expectedData = 10)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'empty_data' => $emptyData,
        ]);
        $form->submit(null);

        self::assertSame($emptyData, $form->getViewData());
        self::assertSame($expectedData, $form->getNormData());
        self::assertSame($expectedData, $form->getData());
    }

    public function testSubmittedLargeIntegersAreNotCastToFloat()
    {
        if (4 === \PHP_INT_SIZE) {
            self::markTestSkipped('This test requires a 64bit PHP.');
        }

        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit('201803221011791');

        self::assertSame(201803221011791, $form->getData());
        self::assertSame('201803221011791', $form->getViewData());
    }

    public function testTooSmallIntegersAreNotValid()
    {
        if (4 === \PHP_INT_SIZE) {
            $min = '-2147483649';
        } else {
            $min = '-9223372036854775808';
        }

        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit($min);

        self::assertFalse($form->isSynchronized());
    }

    public function testTooGreatIntegersAreNotValid()
    {
        if (4 === \PHP_INT_SIZE) {
            $max = '2147483648';
        } else {
            $max = '9223372036854775808';
        }

        $form = $this->factory->create(static::TESTED_TYPE);
        $form->submit($max);

        self::assertFalse($form->isSynchronized());
    }
}
