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

use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\FormError;

final class ColorTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = ColorType::class;

    /**
     * @dataProvider validationShouldPassProvider
     */
    public function testValidationShouldPass(bool $html5, ?string $submittedValue)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'html5' => $html5,
            'trim' => true,
        ]);

        $form->submit($submittedValue);

        $this->assertEmpty($form->getErrors());
    }

    public static function validationShouldPassProvider()
    {
        return [
            [false, 'foo'],
            [false, null],
            [false, ''],
            [false, ' '],
            [true, '#000000'],
            [true, '#abcabc'],
            [true, '#BbBbBb'],
            [true, '#1Ee54d'],
            [true, ' #1Ee54d '],
            [true, null],
            [true, ''],
            [true, ' '],
        ];
    }

    /**
     * @dataProvider validationShouldFailProvider
     */
    public function testValidationShouldFail(string $expectedValueParameterValue, ?string $submittedValue, bool $trim = true)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'html5' => true,
            'trim' => $trim,
        ]);

        $form->submit($submittedValue);

        $expectedFormError = new FormError('This value is not a valid HTML5 color.', 'This value is not a valid HTML5 color.', [
            '{{ value }}' => $expectedValueParameterValue,
        ]);
        $expectedFormError->setOrigin($form);

        $this->assertEquals([$expectedFormError], iterator_to_array($form->getErrors()));
    }

    public static function validationShouldFailProvider()
    {
        return [
            ['foo', 'foo'],
            ['000000', '000000'],
            ['#abcabg', '#abcabg'],
            ['#12345', '#12345'],
            [' #ffffff ', ' #ffffff ', false],
        ];
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }
}
