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

use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\AtLeastOneOfValidator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Country;
use Symfony\Component\Validator\Constraints\DivisibleBy;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\Negative;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class AtLeastOneOfValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new AtLeastOneOfValidator();
    }

    /**
     * @dataProvider getValidCombinations
     */
    public function testValidCombinations($value, $constraints)
    {
        $i = 0;

        foreach ($constraints as $constraint) {
            $this->expectViolationsAt($i++, $value, $constraint);
        }

        $this->validator->validate($value, new AtLeastOneOf($constraints));

        $this->assertNoViolation();
    }

    public function getValidCombinations()
    {
        return [
            ['symfony', [
                new Length(['min' => 10]),
                new EqualTo(['value' => 'symfony']),
            ]],
            [150, [
                new Range(['min' => 10, 'max' => 20]),
                new GreaterThanOrEqual(['value' => 100]),
            ]],
            [7, [
                new LessThan(['value' => 5]),
                new IdenticalTo(['value' => 7]),
            ]],
            [-3, [
                new DivisibleBy(['value' => 4]),
                new Negative(),
            ]],
            ['FOO', [
                new Choice(['choices' => ['bar', 'BAR']]),
                new Regex(['pattern' => '/foo/i']),
            ]],
            ['fr', [
                new Country(),
                new Language(),
            ]],
            [[1, 3, 5], [
                new Count(['min' => 5]),
                new Unique(),
            ]],
        ];
    }

    /**
     * @dataProvider getInvalidCombinations
     */
    public function testInvalidCombinationsWithDefaultMessage($value, $constraints)
    {
        $atLeastOneOf = new AtLeastOneOf(['constraints' => $constraints]);

        $message = [$atLeastOneOf->message];

        $i = 0;

        foreach ($constraints as $constraint) {
            $message[] = ' ['.($i + 1).'] '.$this->expectViolationsAt($i++, $value, $constraint)->get(0)->getMessage();
        }

        $this->validator->validate($value, $atLeastOneOf);

        $this->buildViolation(implode('', $message))->setCode(AtLeastOneOf::AT_LEAST_ONE_OF_ERROR)->assertRaised();
    }

    /**
     * @dataProvider getInvalidCombinations
     */
    public function testInvalidCombinationsWithCustomMessage($value, $constraints)
    {
        $atLeastOneOf = new AtLeastOneOf(['constraints' => $constraints, 'message' => 'foo', 'includeInternalMessages' => false]);

        $i = 0;

        foreach ($constraints as $constraint) {
            $this->expectViolationsAt($i++, $value, $constraint);
        }

        $this->validator->validate($value, $atLeastOneOf);

        $this->buildViolation('foo')->setCode(AtLeastOneOf::AT_LEAST_ONE_OF_ERROR)->assertRaised();
    }

    public function getInvalidCombinations()
    {
        return [
            ['symphony', [
                new Length(['min' => 10]),
                new EqualTo(['value' => 'symfony']),
            ]],
            [70, [
                new Range(['min' => 10, 'max' => 20]),
                new GreaterThanOrEqual(['value' => 100]),
            ]],
            [8, [
                new LessThan(['value' => 5]),
                new IdenticalTo(['value' => 7]),
            ]],
            [3, [
                new DivisibleBy(['value' => 4]),
                new Negative(),
            ]],
            ['F_O_O', [
                new Choice(['choices' => ['bar', 'BAR']]),
                new Regex(['pattern' => '/foo/i']),
            ]],
            ['f_r', [
                new Country(),
                new Language(),
            ]],
            [[1, 3, 3], [
                new Count(['min' => 5]),
                new Unique(),
            ]],
        ];
    }
}
