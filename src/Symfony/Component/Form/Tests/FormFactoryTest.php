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
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $builder;

    /**
     * @var FormFactory
     */
    private $factory;

    protected function setUp()
    {
        $this->resolvedTypeFactory = $this->getMock('Symfony\Component\Form\ResolvedFormTypeFactoryInterface');
        $this->guesser1 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
        $this->guesser2 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
        $this->registry = $this->getMock('Symfony\Component\Form\FormRegistryInterface');
        $this->builder = $this->getMock('Symfony\Component\Form\Test\FormBuilderInterface');
        $this->factory = new FormFactory($this->registry, $this->resolvedTypeFactory);

        $this->registry->expects($this->any())
            ->method('getTypeGuesser')
            ->will($this->returnValue(new FormTypeGuesserChain(array(
                $this->guesser1,
                $this->guesser2,
            ))));
    }

    public function testCreateNamedBuilderWithTypeName()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue($this->builder));

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($resolvedOptions));

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->assertSame($this->builder, $this->factory->createNamedBuilder('name', 'type', null, $options));
    }

    public function testCreateNamedBuilderFillsDataOption()
    {
        $givenOptions = array('a' => '1', 'b' => '2');
        $expectedOptions = array_merge($givenOptions, array('data' => 'DATA'));
        $resolvedOptions = array('a' => '2', 'b' => '3', 'data' => 'DATA');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $expectedOptions)
            ->will($this->returnValue($this->builder));

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($resolvedOptions));

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->assertSame($this->builder, $this->factory->createNamedBuilder('name', 'type', 'DATA', $givenOptions));
    }

    public function testCreateNamedBuilderDoesNotOverrideExistingDataOption()
    {
        $options = array('a' => '1', 'b' => '2', 'data' => 'CUSTOM');
        $resolvedOptions = array('a' => '2', 'b' => '3', 'data' => 'CUSTOM');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue($this->builder));

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($resolvedOptions));

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->assertSame($this->builder, $this->factory->createNamedBuilder('name', 'type', 'DATA', $options));
    }

    /**
     * @expectedException        \Symfony\Component\Form\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "string", "stdClass" given
     */
    public function testCreateNamedBuilderThrowsUnderstandableException()
    {
        $this->factory->createNamedBuilder('name', new \stdClass());
    }

    /**
     * @expectedException        \Symfony\Component\Form\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "string", "stdClass" given
     */
    public function testCreateThrowsUnderstandableException()
    {
        $this->factory->create(new \stdClass());
    }

    public function testCreateUsesBlockPrefixIfTypeGivenAsString()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');

        // the interface does not have the method, so use the real class
        $resolvedType = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormType')
            ->disableOriginalConstructor()
            ->getMock();

        $resolvedType->expects($this->any())
            ->method('getBlockPrefix')
            ->willReturn('TYPE_PREFIX');

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('TYPE')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'TYPE_PREFIX', $options)
            ->will($this->returnValue($this->builder));

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($resolvedOptions));

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
            ->method('getForm')
            ->will($this->returnValue('FORM'));

        $this->assertSame('FORM', $this->factory->create('TYPE', null, $options));
    }

    public function testCreateNamed()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->once())
            ->method('getType')
            ->with('type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'name', $options)
            ->will($this->returnValue($this->builder));

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($resolvedOptions));

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->builder->expects($this->once())
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
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array())
            ->will($this->returnValue('builderInstance'));

        $this->builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testCreateBuilderForPropertyCreatesFormWithHighestConfidence()
    {
        $this->guesser1->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->will($this->returnValue(new TypeGuess(
                'Symfony\Component\Form\Extension\Core\Type\TextType',
                array('attr' => array('maxlength' => 10)),
                Guess::MEDIUM_CONFIDENCE
            )));

        $this->guesser2->expects($this->once())
            ->method('guessType')
            ->with('Application\Author', 'firstName')
            ->will($this->returnValue(new TypeGuess(
                'Symfony\Component\Form\Extension\Core\Type\PasswordType',
                array('attr' => array('maxlength' => 7)),
                Guess::HIGH_CONFIDENCE
            )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\PasswordType', null, array('attr' => array('maxlength' => 7)))
            ->will($this->returnValue('builderInstance'));

        $this->builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $this->builder);
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
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->will($this->returnValue('builderInstance'));

        $this->builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testOptionsCanBeOverridden()
    {
        $this->guesser1->expects($this->once())
                ->method('guessType')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new TypeGuess(
                    'Symfony\Component\Form\Extension\Core\Type\TextType',
                    array('attr' => array('maxlength' => 10)),
                    Guess::MEDIUM_CONFIDENCE
                )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('attr' => array('maxlength' => 11)))
            ->will($this->returnValue('builderInstance'));

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName',
            null,
            array('attr' => array('maxlength' => 11))
        );

        $this->assertEquals('builderInstance', $this->builder);
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
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('attr' => array('maxlength' => 20)))
            ->will($this->returnValue('builderInstance'));

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $this->builder);
    }

    public function testCreateBuilderUsesMaxLengthAndPattern()
    {
        $this->guesser1->expects($this->once())
            ->method('guessMaxLength')
            ->with('Application\Author', 'firstName')
            ->will($this->returnValue(new ValueGuess(
                20,
                Guess::HIGH_CONFIDENCE
            )));

        $this->guesser2->expects($this->once())
            ->method('guessPattern')
            ->with('Application\Author', 'firstName')
            ->will($this->returnValue(new ValueGuess(
                '.{5,}',
                Guess::HIGH_CONFIDENCE
            )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('attr' => array('maxlength' => 20, 'pattern' => '.{5,}', 'class' => 'tinymce')))
            ->will($this->returnValue('builderInstance'));

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName',
            null,
            array('attr' => array('class' => 'tinymce'))
        );

        $this->assertEquals('builderInstance', $this->builder);
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
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('required' => false))
            ->will($this->returnValue('builderInstance'));

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $this->builder);
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
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('attr' => array('pattern' => '[a-zA-Z]')))
            ->will($this->returnValue('builderInstance'));

        $this->builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $this->builder);
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
}
