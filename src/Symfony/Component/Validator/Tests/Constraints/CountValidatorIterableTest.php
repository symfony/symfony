<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Count;

/**
 * @author Andreas Linden <linden.andreas@gmx.de>
 */
abstract class CountValidatorIterableTest extends CountValidatorTest
{
    /**
     * @dataProvider getFourElements
     */
    public function testSimpleConditionExpression($value)
    {
        $constraint = new Count([
            'min' => 2,
            'max' => 2,
            'exactMessage' => 'myMessage',
            'condition' => 'item > 2',
        ]);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getFourElements
     */
    public function testSimpleConditionExpressionFailed($value)
    {
        $constraint = new Count([
            'min' => 2,
            'max' => 2,
            'exactMessage' => 'myMessage',
            'condition' => 'item > 42',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ count }}', 0)
            ->setParameter('{{ limit }}', 2)
            ->setInvalidValue($value)
            ->setPlural(2)
            ->setCode(Count::TOO_FEW_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getFourObjectElements
     */
    public function testObjectConditionExpression($value)
    {
        $constraint = new Count([
            'min' => 2,
            'max' => 2,
            'exactMessage' => 'myMessage',
            'condition' => 'item.getValue() === "value_1"',
        ]);

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getFourObjectElements
     */
    public function testObjectConditionExpressionFailed($value)
    {
        $constraint = new Count([
            'min' => 2,
            'max' => 2,
            'exactMessage' => 'myMessage',
            'condition' => 'item.getValue() === "value_42"',
        ]);

        $this->validator->validate($value, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ count }}', 0)
            ->setParameter('{{ limit }}', 2)
            ->setInvalidValue($value)
            ->setPlural(2)
            ->setCode(Count::TOO_FEW_ERROR)
            ->assertRaised();
    }
}
