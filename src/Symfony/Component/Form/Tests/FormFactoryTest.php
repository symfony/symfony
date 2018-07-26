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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooSubType;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooSubTypeWithParentInstance;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooType;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormFactoryTest extends TestCase
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
        $this->resolvedTypeFactory = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeFactoryInterface')->getMock();
        $this->guesser1 = $this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->guesser2 = $this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->registry = $this->getMockBuilder('Symfony\Component\Form\FormRegistryInterface')->getMock();
        $this->builder = $this->getMockBuilder('Symfony\Component\Form\Test\FormBuilderInterface')->getMock();
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

    /**
     * @group legacy
     */
    public function testCreateNamedBuilderWithTypeInstance()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $type = new LegacyFooType();
        $resolvedType = $this->getMockResolvedType();

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
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

        $this->assertSame($this->builder, $this->factory->createNamedBuilder('name', $type, null, $options));
    }

    /**
     * @group legacy
     */
    public function testCreateNamedBuilderWithTypeInstanceWithParentType()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $type = new LegacyFooSubType();
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
            ->will($this->returnValue($this->builder));

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($resolvedOptions));

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->assertSame($this->builder, $this->factory->createNamedBuilder('name', $type, null, $options));
    }

    /**
     * @group legacy
     */
    public function testCreateNamedBuilderWithTypeInstanceWithParentTypeInstance()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $type = new LegacyFooSubTypeWithParentInstance();
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
            ->will($this->returnValue($this->builder));

        $this->builder->expects($this->any())
            ->method('getOptions')
            ->will($this->returnValue($resolvedOptions));

        $resolvedType->expects($this->once())
            ->method('buildForm')
            ->with($this->builder, $resolvedOptions);

        $this->assertSame($this->builder, $this->factory->createNamedBuilder('name', $type, null, $options));
    }

    /**
     * @group legacy
     */
    public function testCreateNamedBuilderWithResolvedTypeInstance()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

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

        $this->assertSame($this->builder, $this->factory->createNamedBuilder('name', $resolvedType, null, $options));
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
     * @expectedExceptionMessage Expected argument of type "string, Symfony\Component\Form\ResolvedFormTypeInterface or Symfony\Component\Form\FormTypeInterface", "stdClass" given
     */
    public function testCreateNamedBuilderThrowsUnderstandableException()
    {
        $this->factory->createNamedBuilder('name', new \stdClass());
    }

    /**
     * @expectedException        \Symfony\Component\Form\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "string, Symfony\Component\Form\ResolvedFormTypeInterface or Symfony\Component\Form\FormTypeInterface", "stdClass" given
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

    /**
     * @group legacy
     */
    public function testCreateUsesTypeNameIfTypeGivenAsString()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('TYPE')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'TYPE', $options)
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

    /**
     * @group legacy
     */
    public function testCreateStripsNamespaceOffTypeName()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('Vendor\Name\Space\UserForm')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'user_form', $options)
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

        $this->assertSame('FORM', $this->factory->create('Vendor\Name\Space\UserForm', null, $options));
    }

    /**
     * @group legacy
     */
    public function testLegacyCreateStripsNamespaceOffTypeNameAccessByFQCN()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('userform')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'userform', $options)
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

        $this->assertSame('FORM', $this->factory->create('userform', null, $options));
    }

    /**
     * @group legacy
     */
    public function testCreateStripsTypeSuffixOffTypeName()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('Vendor\Name\Space\UserType')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'user', $options)
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

        $this->assertSame('FORM', $this->factory->create('Vendor\Name\Space\UserType', null, $options));
    }

    /**
     * @group legacy
     */
    public function testCreateDoesNotStripTypeSuffixIfResultEmpty()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('Vendor\Name\Space\Type')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'type', $options)
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

        $this->assertSame('FORM', $this->factory->create('Vendor\Name\Space\Type', null, $options));
    }

    /**
     * @group legacy
     */
    public function testCreateConvertsTypeToUnderscoreSyntax()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $this->registry->expects($this->any())
            ->method('getType')
            ->with('Vendor\Name\Space\MyProfileHTMLType')
            ->will($this->returnValue($resolvedType));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'my_profile_html', $options)
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

        $this->assertSame('FORM', $this->factory->create('Vendor\Name\Space\MyProfileHTMLType', null, $options));
    }

    /**
     * @group legacy
     */
    public function testCreateUsesTypeNameIfTypeGivenAsObject()
    {
        $options = array('a' => '1', 'b' => '2');
        $resolvedOptions = array('a' => '2', 'b' => '3');
        $resolvedType = $this->getMockResolvedType();

        $resolvedType->expects($this->once())
            ->method('getName')
            ->will($this->returnValue('TYPE'));

        $resolvedType->expects($this->once())
            ->method('createBuilder')
            ->with($this->factory, 'TYPE', $options)
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

        $this->assertSame('FORM', $this->factory->create($resolvedType, null, $options));
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
        $registry = $this->getMockBuilder('Symfony\Component\Form\FormRegistryInterface')->getMock();
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
                array('attr' => array('class' => 'foo', 'maxlength' => 10)),
                Guess::MEDIUM_CONFIDENCE
            )));

        $factory = $this->getMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array('attr' => array('class' => 'foo', 'maxlength' => 11)))
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
        return $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeInterface')->getMock();
    }
}
