<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\FooType;
use Symfony\Component\Form\Tests\Fixtures\FooSubType;
use Symfony\Component\Form\Tests\Fixtures\FooSubTypeWithParentInstance;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $guesser1;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $guesser2;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $registry;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $resolvedTypeFactory;

    /**
     * @var FormFactory
     */
    private $factory;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        $this->resolvedTypeFactory = $this->getMock('Symfony\Component\Form\ResolvedFormTypeFactoryInterface');
        $this->guesser1 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
        $this->guesser2 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
        $this->registry = $this->getMock('Symfony\Component\Form\FormRegistryInterface');
        $this->factory = new FormFactory($this->registry, $this->resolvedTypeFactory);

        $this->registry->expects($this->any())
            ->method('getTypeGuesser')
            ->will($this->returnValue(new FormTypeGuesserChain(array(
                $this->guesser1,
                $this->guesser2,
            ))));
    }

    public function testAddType()
    {
        $type = new FooType();
        $resolvedType = $this->getMockResolvedType();

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->will($this->returnValue($resolvedType));

        $this->registry->expects($this->once())
            ->method('addType')
            ->with($resolvedType);

        set_error_handler(array('Symfony\Component\Form\Test\DeprecationErrorHandler', 'handle'));
        $this->factory->addType($type);
        restore_error_handler();
    }

    public function testHasType()
    {
        $this->registry->expects($this->once())
            ->method('hasType')
            ->with('name')
            ->will($this->returnValue('RESULT'));

        set_error_handler(array('Symfony\Component\Form\Test\DeprecationErrorHandler', 'handle'));
        $this->assertSame('RESULT', $this->factory->hasType('name'));
        restore_error_handler();
    }

    public function testGetType()
    {
        $type = new FooType();
        $resolvedType = $this->getMockResolvedType();

        $resolvedType->expects($this->once())
            ->method('getInnerType')
            ->will($this->returnValue($type));

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('name')
            ->will($this->returnValue($resolvedType));

        set_error_handler(array('Symfony\Component\Form\Test\DeprecationErrorHandler', 'handle'));
        $this->assertEquals($type, $this->factory->getType('name'));
        restore_error_handler();
    }

    public function testCreateNamedBuilderWithTypeName()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue('BUILDER'));

        $this->assertSame('BUILDER', $this->factory->createNamedBuilder('name', 'type', null, $options));
    }

    public function testCreateNamedBuilderWithTypeInstance()
    {
        $options = array('a' => '1', 'b' => '2');
        $type = new FooType();
        $resolvedType = $this->getMockResolvedType();

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue('BUILDER'));

        $this->assertSame('BUILDER', $this->factory->createNamedBuilder('name', $type, null, $options));
    }

    public function testCreateNamedBuilderWithTypeInstanceWithParentType()
    {
        $options = array('a' => '1', 'b' => '2');
        $type = new FooSubType();
        $resolvedType = $this->getMockResolvedType();
        $parentResolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('foo')
            ->will($this->returnValue($parentResolvedType));

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type, array(), $parentResolvedType)
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue('BUILDER'));

        $this->assertSame('BUILDER', $this->factory->createNamedBuilder('name', $type, null, $options));
    }

    public function testCreateNamedBuilderWithTypeInstanceWithParentTypeInstance()
    {
        $options = array('a' => '1', 'b' => '2');
        $type = new FooSubTypeWithParentInstance();
        $resolvedType = $this->getMockResolvedType();
        $parentResolvedType = $this->getMockResolvedType();

        $this->resolvedTypeFactory->expects($this->at(0))
            ->method('createResolvedType')
            ->with($type->getParent())
            ->will($this->returnValue($parentResolvedType));

        $this->resolvedTypeFactory->expects($this->at(1))
            ->method('createResolvedType')
            ->with($type, array(), $parentResolvedType)
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue('BUILDER'));

        $this->assertSame('BUILDER', $this->factory->createNamedBuilder('name', $type, null, $options));
    }

    public function testCreateNamedBuilderWithResolvedTypeInstance()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedType = $this->getMockResolvedType();

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue('BUILDER'));

        $this->assertSame('BUILDER', $this->factory->createNamedBuilder('name', $resolvedType, null, $options));
    }

    public function testCreateNamedBuilderWithParentBuilder()
    {
        $options = array('a' => '1', 'b' => '2');
        $parentBuilder = $this->getMockFormBuilder();
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options, $parentBuilder)
            ->will($this->returnValue('BUILDER'));

        $this->assertSame('BUILDER', $this->factory->createNamedBuilder('name', 'type', null, $options, $parentBuilder));
    }

    public function testCreateNamedBuilderFillsDataOption()
    {
        $givenOptions = array('a' => '1', 'b' => '2');
        $expectedOptions = array_merge($givenOptions, array('data' => 'DATA'));
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $expectedOptions)
            ->will($this->returnValue('BUILDER'));

        $this->assertSame('BUILDER', $this->factory->createNamedBuilder('name', 'type', 'DATA', $givenOptions));
    }

    public function testCreateNamedBuilderDoesNotOverrideExistingDataOption()
    {
        $options = array('a' => '1', 'b' => '2', 'data' => 'CUSTOM');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue('BUILDER'));

        $this->assertSame('BUILDER', $this->factory->createNamedBuilder('name', 'type', 'DATA', $options));
    }

    /**
     * @expectedException        \Symfony\Component\Form\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "string, Symfony\Component\Form\ResolvedFormTypeInterface or Symfony\Component\Form\FormTypeInterface", "stdClass" given
     */
    public function testCreateNamedBuilderThrowsUnderstandableException()
    {
        $this->factory->createNamedBuilder('name', new \stdClass());
    }

    public function testCreateUsesTypeNameIfTypeGivenAsString()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedType = $this->getMockResolvedType();
        $builder = $this->getMockFormBuilder();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('TYPE')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'TYPE', $options)
            ->will($this->returnValue($builder));

        $builder->expects($this->once())
            ->method('getForm')
            ->will($this->returnValue('FORM'));

        $this->assertSame('FORM', $this->factory->create('TYPE', null, $options));
    }

    public function testCreateUsesTypeNameIfTypeGivenAsObject()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedType = $this->getMockResolvedType();
        $builder = $this->getMockFormBuilder();

        $resolvedType->expects($this->once())
            ->method('getName')
            ->will($this->returnValue('TYPE'));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'TYPE', $options)
            ->will($this->returnValue($builder));

        $builder->expects($this->once())
            ->method('getForm')
            ->will($this->returnValue('FORM'));

        $this->assertSame('FORM', $this->factory->create($resolvedType, null, $options));
    }

    public function testCreateNamed()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedType = $this->getMockResolvedType();
        $builder = $this->getMockFormBuilder();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue($builder));

        $builder->expects($this->once())
            ->method('getForm')
            ->will($this->returnValue('FORM'));

        $this->assertSame('FORM', $this->factory->createNamed('name', 'type', null, $options));
    }

    public function testCreateBuilderForPropertyWithoutTypeGuesser()
    {
        $registry = $this->getMock('Symfony\Component\Form\FormRegistryInterface');
        $factory = $this->getMockBuilder('Symfony\Component\Form\FormFactory')
            ->setMethods(array('createNamedBuilder'))
            ->setConstructorArgs(array($registry, $this->resolvedTypeFactory))
            ->getMock();

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'text', null, array())
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderForPropertyCreatesFormWithHighestConfidence()
    {
        $this->guesser1->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->will($this->returnValue(new TypeGuess(
                'text',
                array('max_length' => 10),
                Guess::MEDIUM_CONFIDENCE
            )));

        $this->guesser2->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->will($this->returnValue(new TypeGuess(
                'password',
                array('max_length' => 7),
                Guess::HIGH_CONFIDENCE
            )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'password', null, array('max_length' => 7))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderCreatesTextFormIfNoGuess()
    {
        $this->guesser1->expects($this->once())
                ->method('guessType')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(null));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'text')
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $builder);
    }

    public function testOptionsCanBeOverridden()
    {
        $this->guesser1->expects($this->once())
                ->method('guessType')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new TypeGuess(
                    'text',
                    array('max_length' => 10),
                    Guess::MEDIUM_CONFIDENCE
                )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'text', null, array('max_length' => 11))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName',
            null,
            array('max_length' => 11)
        );

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderUsesMaxLengthIfFound()
    {
        $this->guesser1->expects($this->once())
                ->method('guessMaxLength')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    15,
                    Guess::MEDIUM_CONFIDENCE
                )));

        $this->guesser2->expects($this->once())
                ->method('guessMaxLength')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    20,
                    Guess::HIGH_CONFIDENCE
                )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'text', null, array('max_length' => 20))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderUsesMinLengthIfFound()
    {
        $this->guesser1->expects($this->once())
                ->method('guessMinLength')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    2,
                    Guess::MEDIUM_CONFIDENCE
                )));

        $this->guesser2->expects($this->once())
                ->method('guessMinLength')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    5,
                    Guess::HIGH_CONFIDENCE
                )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'text', null, array('pattern' => '.{5,}'))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderPrefersPatternOverMinLength()
    {
        // min length is deprecated
        $this->guesser1->expects($this->once())
                ->method('guessMinLength')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    2,
                    Guess::HIGH_CONFIDENCE
                )));

        // pattern is preferred even though confidence is lower
        $this->guesser2->expects($this->once())
                ->method('guessPattern')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    '.{5,10}',
                    Guess::LOW_CONFIDENCE
                )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'text', null, array('pattern' => '.{5,10}'))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderUsesRequiredSettingWithHighestConfidence()
    {
        $this->guesser1->expects($this->once())
                ->method('guessRequired')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    true,
                    Guess::MEDIUM_CONFIDENCE
                )));

        $this->guesser2->expects($this->once())
                ->method('guessRequired')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    false,
                    Guess::HIGH_CONFIDENCE
                )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'text', null, array('required' => false))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderUsesPatternIfFound()
    {
        $this->guesser1->expects($this->once())
                ->method('guessPattern')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    '[a-z]',
                    Guess::MEDIUM_CONFIDENCE
                )));

        $this->guesser2->expects($this->once())
                ->method('guessPattern')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    '[a-zA-Z]',
                    Guess::HIGH_CONFIDENCE
                )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'text', null, array('pattern' => '[a-zA-Z]'))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $builder);
    }

    private function getMockFactory(array $methods = array())
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormFactory')
            ->setMethods($methods)
            ->setConstructorArgs(array($this->registry, $this->resolvedTypeFactory))
            ->getMock();
    }

    private function getMockResolvedType()
    {
        return $this->getMock('Symfony\Component\Form\ResolvedFormTypeInterface');
    }

    private function getMockType()
    {
        return $this->getMock('Symfony\Component\Form\FormTypeInterface');
    }

    private function getMockFormBuilder()
    {
        return $this->getMock('Symfony\Component\Form\Test\FormBuilderInterface');
    }
}
