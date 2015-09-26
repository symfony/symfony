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

use Symfony\Component\Form\Extension\Validator\ValidatorTypeGuesser;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * @author franek <franek@chicour.net>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidatorTypeGuesserTest extends \PHPUnit_Framework_TestCase
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
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $metadataFactory;

    protected function setUp()
    {
        $this->metadata = new ClassMetadata(self::TEST_CLASS);
        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface');
        $this->metadataFactory->expects($this->any())
            ->method('getMetadataFor')
            ->with(self::TEST_CLASS)
            ->will($this->returnValue($this->metadata));
        $this->guesser = new ValidatorTypeGuesser($this->metadataFactory);
    }

    public function guessRequiredProvider()
    {
        return array(
            array(new NotNull(), new ValueGuess(true, Guess::HIGH_CONFIDENCE)),
            array(new NotBlank(), new ValueGuess(true, Guess::HIGH_CONFIDENCE)),
            array(new IsTrue(), new ValueGuess(true, Guess::HIGH_CONFIDENCE)),
            array(new Length(10), new ValueGuess(false, Guess::LOW_CONFIDENCE)),
            array(new Range(array('min' => 1, 'max' => 20)), new ValueGuess(false, Guess::LOW_CONFIDENCE)),
        );
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

    /**
     * @group legacy
     */
    public function testLegacyGuessRequired()
    {
        if (PHP_VERSION_ID >= 70000) {
            $this->markTestSkipped('Cannot use a class called True on PHP 7 or higher.');
        }
        $true = 'Symfony\Component\Validator\Constraints\True';
        $this->testGuessRequired(new $true(), new ValueGuess(true, Guess::HIGH_CONFIDENCE));
    }

    public function testGuessRequiredReturnsFalseForUnmappedProperties()
    {
        $this->assertEquals(new ValueGuess(false, Guess::LOW_CONFIDENCE), $this->guesser->guessRequired(self::TEST_CLASS, self::TEST_PROPERTY));
    }

    public function testGuessMaxLengthForConstraintWithMaxValue()
    {
        $constraint = new Length(array('max' => '2'));

        $result = $this->guesser->guessMaxLengthForConstraint($constraint);
        $this->assertInstanceOf('Symfony\Component\Form\Guess\ValueGuess', $result);
        $this->assertEquals(2, $result->getValue());
        $this->assertEquals(Guess::HIGH_CONFIDENCE, $result->getConfidence());
    }

    public function testGuessMaxLengthForConstraintWithMinValue()
    {
        $constraint = new Length(array('min' => '2'));

        $result = $this->guesser->guessMaxLengthForConstraint($constraint);
        $this->assertNull($result);
    }

    public function maxLengthTypeProvider()
    {
        return array(
            array('double'),
            array('float'),
            array('numeric'),
            array('real'),
        );
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
