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
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\Negative;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Unique;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Mapping\MetadataInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

/**
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class AtLeastOneOfValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): AtLeastOneOfValidator
    {
        return new AtLeastOneOfValidator();
    }

    /**
     * @dataProvider getValidCombinations
     */
    public function testValidCombinations($value, $constraints)
    {
        $this->assertCount(0, Validation::createValidator()->validate($value, new AtLeastOneOf($constraints)));
    }

    public static function getValidCombinations()
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
        $validator = Validation::createValidator();

        $message = [$atLeastOneOf->message];

        $i = 0;

        foreach ($constraints as $constraint) {
            $message[] = sprintf(' [%d] %s', ++$i, $validator->validate($value, $constraint)->get(0)->getMessage());
        }

        $violations = $validator->validate($value, $atLeastOneOf);

        $this->assertCount(1, $violations, sprintf('1 violation expected. Got %u.', \count($violations)));
        $this->assertEquals(new ConstraintViolation(implode('', $message), implode('', $message), [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $atLeastOneOf), $violations->get(0));
    }

    /**
     * @dataProvider getInvalidCombinations
     */
    public function testInvalidCombinationsWithCustomMessage($value, $constraints)
    {
        $atLeastOneOf = new AtLeastOneOf(['constraints' => $constraints, 'message' => 'foo', 'includeInternalMessages' => false]);

        $violations = Validation::createValidator()->validate($value, $atLeastOneOf);

        $this->assertCount(1, $violations, sprintf('1 violation expected. Got %u.', \count($violations)));
        $this->assertEquals(new ConstraintViolation('foo', 'foo', [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $atLeastOneOf), $violations->get(0));
    }

    public static function getInvalidCombinations()
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

    public function testGroupsArePropagatedToNestedConstraints()
    {
        $validator = Validation::createValidator();

        $violations = $validator->validate(50, new AtLeastOneOf([
            'constraints' => [
                new Range([
                    'groups' => 'non_default_group',
                    'min' => 10,
                    'max' => 20,
                ]),
                new Range([
                    'groups' => 'non_default_group',
                    'min' => 30,
                    'max' => 40,
                ]),
            ],
            'groups' => 'non_default_group',
        ]), 'non_default_group');

        $this->assertCount(1, $violations);
    }

    public function testContextIsPropagatedToNestedConstraints()
    {
        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory(new class() implements MetadataFactoryInterface {
                public function getMetadataFor($classOrObject): MetadataInterface
                {
                    return (new ClassMetadata(ExpressionConstraintNested::class))
                        ->addPropertyConstraint('foo', new AtLeastOneOf([
                            new NotNull(),
                            new Expression('this.getFoobar() in ["bar", "baz"]'),
                        ]));
                }

                public function hasMetadataFor($classOrObject): bool
                {
                    return ExpressionConstraintNested::class === $classOrObject;
                }
            })
            ->getValidator()
        ;

        $violations = $validator->validate(new ExpressionConstraintNested(), new Valid());

        $this->assertCount(0, $violations);
    }

    public function testEmbeddedMessageTakenFromFailingConstraint()
    {
        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory(new class() implements MetadataFactoryInterface {
                public function getMetadataFor($classOrObject): MetadataInterface
                {
                    return (new ClassMetadata(Data::class))
                        ->addPropertyConstraint('foo', new NotNull(['message' => 'custom message foo']))
                        ->addPropertyConstraint('bar', new AtLeastOneOf([
                            new NotNull(['message' => 'custom message bar']),
                        ]))
                    ;
                }

                public function hasMetadataFor($classOrObject): bool
                {
                    return Data::class === $classOrObject;
                }
            })
            ->getValidator()
        ;

        $violations = $validator->validate(new Data(), new Valid());

        $this->assertCount(2, $violations);
        $this->assertSame('custom message foo', $violations->get(0)->getMessage());
        $this->assertSame('This value should satisfy at least one of the following constraints: [1] custom message bar', $violations->get(1)->getMessage());
    }

    public function testNestedConstraintsAreNotExecutedWhenGroupDoesNotMatch()
    {
        $validator = Validation::createValidator();

        $violations = $validator->validate(50, new AtLeastOneOf([
            'constraints' => [
                new Range([
                    'groups' => 'adult',
                    'min' => 18,
                    'max' => 55,
                ]),
                new GreaterThan([
                    'groups' => 'senior',
                    'value' => 55,
                ]),
            ],
            'groups' => ['adult', 'senior'],
        ]), 'senior');

        $this->assertCount(1, $violations);
    }

    public function testTranslatorIsCalledOnConstraintBaseMessageAndViolations()
    {
        $translator = new class() implements TranslatorInterface, LocaleAwareInterface {
            use TranslatorTrait;

            public function trans(?string $id, array $parameters = [], string $domain = null, string $locale = null): string
            {
                if ('This value should satisfy at least one of the following constraints:' === $id) {
                    return 'Dummy translation:';
                }

                if ('This value should be null.' === $id) {
                    return 'Dummy violation.';
                }

                return $id;
            }
        };

        $validator = Validation::createValidatorBuilder()
            ->setTranslator($translator)
            ->getValidator()
        ;

        $violations = $validator->validate('Test', [
            new AtLeastOneOf([
                new IsNull(),
            ]),
        ]);

        $this->assertCount(1, $violations);
        $this->assertSame('Dummy translation: [1] Dummy violation.', $violations->get(0)->getMessage());
    }
}

class ExpressionConstraintNested
{
    private $foo;

    public function getFoobar(): string
    {
        return 'bar';
    }
}

class Data
{
    public $foo;
    public $bar;
}
