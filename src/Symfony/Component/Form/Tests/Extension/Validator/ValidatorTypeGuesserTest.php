<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;

/**
 * @author franek <franek@chicour.net>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorTypeGuesserTest extends TestCase
{
    const TEST_CLASS = 'Symfony\Component\Form\Tests\Extension\Validator\ValidatorTypeGuesserTest_TestClass';

    const TEST_PROPERTY = 'property';

    /**
     * @var ValidatorTypeGuesser
     */
    private $guesser;

    /**
     * @var ClassMetadata
     */
    private $metadata;

    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    protected function setUp()
    {
        $this->metadata = new ClassMetadata(self::TEST_CLASS);
        $this->metadataFactory = new FakeMetadataFactory();
        $this->metadataFactory->addMetadata($this->metadata);
        $this->guesser = new ValidatorTypeGuesser($this->metadataFactory);
    }

    /**
     * @dataProvider guessTypeProvider
     */
    public function testGuessType(Constraint $constraint, TypeGuess $guess)
    {
        $this->metadata->addPropertyConstraint(self::TEST_PROPERTY, $constraint);

        $this->assertEquals($guess, $this->guesser->guessType(self::TEST_CLASS, self::TEST_PROPERTY));
    }

    public function guessTypeProvider()
    {
        return [
            [new Type('array'), new TypeGuess(CollectionType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('bool'), new TypeGuess(CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('boolean'), new TypeGuess(CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('double'), new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('float'), new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('numeric'), new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('real'), new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('int'), new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('integer'), new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('long'), new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('string'), new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE)],
            [new Type(\DateTime::class), new TypeGuess(DateType::class, [], Guess::MEDIUM_CONFIDENCE)],
            [new Type('\DateTime'), new TypeGuess(DateType::class, [], Guess::MEDIUM_CONFIDENCE)],
        ];
    }

    public function guessRequiredProvider()
    {
        return [
            [new NotNull(), new ValueGuess(true, Guess::HIGH_CONFIDENCE)],
            [new NotBlank(), new ValueGuess(true, Guess::HIGH_CONFIDENCE)],
            [new IsTrue(), new ValueGuess(true, Guess::HIGH_CONFIDENCE)],
            [new Length(10), new ValueGuess(false, Guess::LOW_CONFIDENCE)],
            [new Range(['min' => 1, 'max' => 20]), new ValueGuess(false, Guess::LOW_CONFIDENCE)],
        ];
    }

    /**
     * @dataProvider guessRequiredProvider
     */
    public function testGuessRequired($constraint, $guess)
    {
        // add distracting constraint
        $this->metadata->addPropertyConstraint(self::TEST_PROPERTY, new Email());

        // add constraint under test
        $this->metadata->addPropertyConstraint(self::TEST_PROPERTY, $constraint);

        $this->assertEquals($guess, $this->guesser->guessRequired(self::TEST_CLASS, self::TEST_PROPERTY));
    }

    public function testGuessRequiredReturnsFalseForUnmappedProperties()
    {
        $this->assertEquals(new ValueGuess(false, Guess::LOW_CONFIDENCE), $this->guesser->guessRequired(self::TEST_CLASS, self::TEST_PROPERTY));
    }

    public function testGuessMaxLengthForConstraintWithMaxValue()
    {
        $constraint = new Length(['max' => '2']);

        $result = $this->guesser->guessMaxLengthForConstraint($constraint);
        $this->assertInstanceOf('Symfony\Component\Form\Guess\ValueGuess', $result);
        $this->assertEquals(2, $result->getValue());
        $this->assertEquals(Guess::HIGH_CONFIDENCE, $result->getConfidence());
    }

    public function testGuessMaxLengthForConstraintWithMinValue()
    {
        $constraint = new Length(['min' => '2']);

        $result = $this->guesser->guessMaxLengthForConstraint($constraint);
        $this->assertNull($result);
    }

    public function maxLengthTypeProvider()
    {
        return [
            ['double'],
            ['float'],
            ['numeric'],
            ['real'],
        ];
    }

    /**
     * @dataProvider maxLengthTypeProvider
     */
    public function testGuessMaxLengthForConstraintWithType($type)
    {
        $constraint = new Type($type);

        $result = $this->guesser->guessMaxLengthForConstraint($constraint);
        $this->assertInstanceOf('Symfony\Component\Form\Guess\ValueGuess', $result);
        $this->assertNull($result->getValue());
        $this->assertEquals(Guess::MEDIUM_CONFIDENCE, $result->getConfidence());
    }
}

class ValidatorTypeGuesserTest_TestClass
{
    private $property;
}
