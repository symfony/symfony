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
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\Extension\Core\Type as FormType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;
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

        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface');

        $this->typeGuesser = new ValidatorTypeGuesser($this->metadataFactory);
    }

    public function testGuessOptionsForConstraintWithMaxLength()
    {
        $class = new \stdClass();

        $this->setupMetadata($class, 'foo', array(new Length(array('max' => '2'))));

        $result = $this->typeGuesser->guessOptions($class, 'foo', new ResolvedFormType(new FormType\TextType()));

        $this->assertEquals(array('max_length' => 2), $result);
    }

    public function testGuessOptionsForConstraintWithMinLength()
    {
        $class = new \stdClass();

        $this->setupMetadata($class, 'foo', array(new Length(array('min' => '2'))));

        $result = $this->typeGuesser->guessOptions($class, 'foo', new ResolvedFormType(new FormType\TextType()));

        $this->assertEquals(array(), $result);
    }

    public function testGuessOptionsForConstraintWithMinValue()
    {
        $class = new \stdClass();

        $this->setupMetadata($class, 'foo', array(new Range(array('min' => '2'))));

        $result = $this->typeGuesser->guessOptions($class, 'foo', new ResolvedFormType(new FormType\IntegerType()));

        $this->assertEquals(array('attr' => array('min' => 2)), $result);
    }

    public function testGuessOptionsForConstraintWithMaxValue()
    {
        $class = new \stdClass();

        $this->setupMetadata($class, 'foo', array(new Range(array('max' => '2'))));

        $result = $this->typeGuesser->guessOptions($class, 'foo', new ResolvedFormType(new FormType\IntegerType()));

        $this->assertEquals(array('attr' => array('max' => 2)), $result);
    }

    public function testGuessOptionsForConstraintWithMinAndMaxValue()
    {
        $class = new \stdClass();

        $this->setupMetadata($class, 'foo', array(new Range(array('min' => 1, 'max' => '2'))));

        $result = $this->typeGuesser->guessOptions($class, 'foo', new ResolvedFormType(new FormType\IntegerType()));

        $this->assertEquals(array('attr' => array('min' => 1, 'max' => 2)), $result);
    }

    public function testGuessMaxValueForConstraintWithMinValue()
    {
        $constraint = new Range(array('min' => '2'));

        $result = $this->typeGuesser->guessMaxValueForConstraint($constraint);
        $this->assertNull($result);
    }

    private function setupMetadata($class, $property, array $constraints)
    {
        $this->elementMetadata = $this->getMock('Symfony\Component\Validator\Mapping\ElementMetadata');
        $this->elementMetadata->expects($this->any())
            ->method('getConstraints')
            ->will($this->returnValue($constraints));

        $this->metadata = $this->getMockBuilder('Symfony\Component\Validator\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();
        $this->metadata->expects($this->any())
            ->method('hasMemberMetadatas')
            ->with($property)
            ->will($this->returnValue(true));
        $this->metadata->expects($this->any())
            ->method('getMemberMetadatas')
            ->with($property)
            ->will($this->returnValue(array($this->elementMetadata)));

        $this->metadataFactory->expects($this->any())
            ->method('getMetadataFor')
            ->with($class)
            ->will($this->returnValue($this->metadata));
    }
}
