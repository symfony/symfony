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
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Type;

/**
* @author franek <franek@chicour.net>
*/
class ValidatorTypeGuesserTest extends \PHPUnit_Framework_TestCase
{
    private $typeGuesser;

    public function setUp()
    {
        if (!class_exists('Symfony\Component\Validator\Constraint')) {
            $this->markTestSkipped('The "Validator" component is not available');
        }

        $metadataFactory = $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface');

        $this->typeGuesser = new ValidatorTypeGuesser($metadataFactory);
    }

    public function testGuessMaxLengthForConstraintWithMaxValue()
    {
        $constraint = new Length(array('max' => '2'));

        $result = $this->typeGuesser->guessMaxLengthForConstraint($constraint);
        $this->assertInstanceOf('Symfony\Component\Form\Guess\ValueGuess', $result);
        $this->assertEquals(2, $result->getValue());
        $this->assertEquals(Guess::HIGH_CONFIDENCE, $result->getConfidence());
    }

    public function testGuessMaxLengthForConstraintWithMinValue()
    {
        $constraint = new Length(array('min' => '2'));

        $result = $this->typeGuesser->guessMaxLengthForConstraint($constraint);
        $this->assertNull($result);
    }

    /**
* @dataProvider dataProviderTestGuessMaxLengthForConstraintWithType
*/
    public function testGuessMaxLengthForConstraintWithType($type)
    {
        $constraint = new Type($type);

        $result = $this->typeGuesser->guessMaxLengthForConstraint($constraint);
        $this->assertInstanceOf('Symfony\Component\Form\Guess\ValueGuess', $result);
        $this->assertEquals(null, $result->getValue());
        $this->assertEquals(Guess::MEDIUM_CONFIDENCE, $result->getConfidence());
    }

    public static function dataProviderTestGuessMaxLengthForConstraintWithType()
    {
        return array (
            array('double'),
            array('float'),
            array('numeric'),
            array('real')
        );
    }
}
