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

use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Test\TypeTestCase;

class PercentTypeTest extends TypeTestCase
{
    use ExpectDeprecationTrait;

    const TESTED_TYPE = PercentType::class;

    public function testSubmitWithRoundingMode()
    {
        $form = $this->factory->create(self::TESTED_TYPE, null, [
            'scale' => 2,
            'rounding_mode' => \NumberFormatter::ROUND_CEILING,
        ]);

        $form->submit('1.23456');

        $this->assertEquals(0.0124, $form->getData());
    }

    /**
     * @group legacy
     */
    public function testSubmitWithoutRoundingMode()
    {
        $this->expectDeprecation('Since symfony/form 5.1: Not configuring the "rounding_mode" option is deprecated. It will default to "\NumberFormatter::ROUND_HALFUP" in Symfony 6.0.');

        $form = $this->factory->create(self::TESTED_TYPE, null, [
            'scale' => 2,
        ]);

        $form->submit('1.23456');

        $this->assertEquals(0.0123456, $form->getData());
    }

    /**
     * @group legacy
     */
    public function testSubmitWithNullRoundingMode()
    {
        $this->expectDeprecation('Since symfony/form 5.1: Not configuring the "rounding_mode" option is deprecated. It will default to "\NumberFormatter::ROUND_HALFUP" in Symfony 6.0.');

        $form = $this->factory->create(self::TESTED_TYPE, null, [
            'rounding_mode' => null,
            'scale' => 2,
        ]);

        $form->submit('1.23456');

        $this->assertEquals(0.0123456, $form->getData());
    }
}
