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

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\AuthorType;
use Symfony\Component\Form\Tests\Fixtures\TestExtension;
use Symfony\Component\Form\Tests\Fixtures\FooType;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBarExtension;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBazExtension;

class FormFactoryTest extends \PHPUnit_Framework_TestCase
{
    private $extension1;

    private $extension2;

    private $guesser1;

    private $guesser2;

    private $factory;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        $this->guesser1 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
        $this->guesser2 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
        $this->extension1 = new TestExtension($this->guesser1);
        $this->extension2 = new TestExtension($this->guesser2);
        $this->factory = new FormFactory(array($this->extension1, $this->extension2));
    }

    protected function tearDown()
    {
        $this->extension1 = null;
        $this->extension2 = null;
        $this->guesser1 = null;
        $this->guesser2 = null;
        $this->factory = null;
    }

    public function testAddType()
    {
        $this->assertFalse($this->factory->hasType('foo'));

        $type = new FooType();
        $this->factory->addType($type);

        $this->assertTrue($this->factory->hasType('foo'));
        $this->assertSame($type, $this->factory->getType('foo'));
    }

    public function testAddTypeAddsExtensions()
    {
        $type = new FooType();
        $ext1 = new FooTypeBarExtension();
        $ext2 = new FooTypeBazExtension();

        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);

        $this->factory->addType($type);

        $this->assertEquals(array($ext1, $ext2), $type->getExtensions());
    }

    public function testGetTypeFromExtension()
    {
        $type = new FooType();
        $this->extension2->addType($type);

        $this->assertSame($type, $this->factory->getType('foo'));
    }

    public function testGetTypeAddsExtensions()
    {
        $type = new FooType();
        $ext1 = new FooTypeBarExtension();
        $ext2 = new FooTypeBazExtension();

        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);
        $this->extension2->addType($type);

        $type = $this->factory->getType('foo');

        $this->assertEquals(array($ext1, $ext2), $type->getExtensions());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testGetTypeExpectsExistingType()
    {
        $this->factory->getType('bar');
    }

    public function testCreateNamedBuilder()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $builder = $this->factory->createNamedBuilder('bar', 'foo');

        $this->assertTrue($builder instanceof FormBuilder);
        $this->assertEquals('bar', $builder->getName());
        $this->assertNull($builder->getParent());
    }

    public function testCreateNamedBuilderCallsBuildFormMethods()
    {
        $type = new FooType();
        $ext1 = new FooTypeBarExtension();
        $ext2 = new FooTypeBazExtension();

        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);
        $this->extension2->addType($type);

        $builder = $this->factory->createNamedBuilder('bar', 'foo');

        $this->assertTrue($builder->hasAttribute('foo'));
        $this->assertTrue($builder->hasAttribute('bar'));
        $this->assertTrue($builder->hasAttribute('baz'));
    }

    public function testCreateNamedBuilderFillsDataOption()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $builder = $this->factory->createNamedBuilder('bar', 'foo', 'xyz');

        // see FooType::buildForm()
        $this->assertEquals('xyz', $builder->getAttribute('data_option'));
    }

    public function testCreateNamedBuilderDoesNotOverrideExistingDataOption()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $builder = $this->factory->createNamedBuilder('bar', 'foo', 'xyz', array(
            'data' => 'abc',
        ));

        // see FooType::buildForm()
        $this->assertEquals('abc', $builder->getAttribute('data_option'));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\TypeDefinitionException
     */
    public function testCreateNamedBuilderExpectsDataOptionToBeSupported()
    {
        $type = $this->getMock('Symfony\Component\Form\FormTypeInterface');
        $type->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('foo'));
        $type->expects($this->any())
            ->method('getExtensions')
            ->will($this->returnValue(array()));

        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('bar', 'foo');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\TypeDefinitionException
     */
    public function testCreateNamedBuilderExpectsRequiredOptionToBeSupported()
    {
        $type = $this->getMock('Symfony\Component\Form\FormTypeInterface');
        $type->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('foo'));
        $type->expects($this->any())
            ->method('getExtensions')
            ->will($this->returnValue(array()));

        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('bar', 'foo');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\TypeDefinitionException
     */
    public function testCreateNamedBuilderExpectsMaxLengthOptionToBeSupported()
    {
        $type = $this->getMock('Symfony\Component\Form\FormTypeInterface');
        $type->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('foo'));
        $type->expects($this->any())
            ->method('getExtensions')
            ->will($this->returnValue(array()));

        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('bar', 'foo');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\TypeDefinitionException
     */
    public function testCreateNamedBuilderExpectsBuilderToBeReturned()
    {
        $type = $this->getMock('Symfony\Component\Form\FormTypeInterface');
        $type->expects($this->any())
            ->method('getName')
            ->will($this->returnValue('foo'));
        $type->expects($this->any())
            ->method('getExtensions')
            ->will($this->returnValue(array()));
        $type->expects($this->any())
            ->method('createBuilder')
            ->will($this->returnValue(null));

        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('bar', 'foo');
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testCreateNamedBuilderExpectsOptionsToExist()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('bar', 'foo', null, array(
            'invalid' => 'xyz',
        ));
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testCreateNamedBuilderExpectsOptionsToBeInValidRange()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('bar', 'foo', null, array(
            'a_or_b' => 'c',
        ));
    }

    public function testCreateNamedBuilderAllowsExtensionsToExtendAllowedOptionValues()
    {
        $type = new FooType();
        $this->extension1->addType($type);
        $this->extension1->addTypeExtension(new FooTypeBarExtension());

        // no exception this time
        $this->factory->createNamedBuilder('bar', 'foo', null, array(
            'a_or_b' => 'c',
        ));
    }

    public function testCreateNamedBuilderAddsTypeInstances()
    {
        $type = new FooType();
        $this->assertFalse($this->factory->hasType('foo'));

        $builder = $this->factory->createNamedBuilder('bar', $type);

        $this->assertTrue($builder instanceof FormBuilder);
        $this->assertTrue($this->factory->hasType('foo'));
    }

    /**
     * @expectedException        Symfony\Component\Form\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "string or Symfony\Component\Form\FormTypeInterface", "stdClass" given
     */
    public function testCreateNamedBuilderThrowsUnderstandableException()
    {
        $this->factory->createNamedBuilder('name', new \stdClass());
    }

    public function testCreateUsesTypeNameAsName()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $builder = $this->factory->createBuilder('foo');

        $this->assertEquals('foo', $builder->getName());
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

        $factory = $this->createMockFactory(array('createNamedBuilder'));

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

        $factory = $this->createMockFactory(array('createNamedBuilder'));

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

        $factory = $this->createMockFactory(array('createNamedBuilder'));

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

        $factory = $this->createMockFactory(array('createNamedBuilder'));

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

        $factory = $this->createMockFactory(array('createNamedBuilder'));

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

        $factory = $this->createMockFactory(array('createNamedBuilder'));

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

        $factory = $this->createMockFactory(array('createNamedBuilder'));

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
                    '/[a-z]/',
                    Guess::MEDIUM_CONFIDENCE
                )));

        $this->guesser2->expects($this->once())
                ->method('guessPattern')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(new ValueGuess(
                    '/[a-zA-Z]/',
                    Guess::HIGH_CONFIDENCE
                )));

        $factory = $this->createMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('firstName', 'text', null, array('pattern' => '/[a-zA-Z]/'))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateNamedBuilderFromParentBuilder()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $parentBuilder = $this->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->setConstructorArgs(array('name', null, $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'), $this->factory))
            ->getMock()
        ;

        $builder = $this->factory->createNamedBuilder('bar', 'foo', null, array(), $parentBuilder);

        $this->assertNotEquals($builder, $builder->getParent());
        $this->assertEquals($parentBuilder, $builder->getParent());
    }

    public function testFormTypeCreatesDefaultValueForEmptyDataOption()
    {
        $factory = new FormFactory(array(new \Symfony\Component\Form\Extension\Core\CoreExtension()));

        $form = $factory->createNamedBuilder('author', new AuthorType())->getForm();
        $form->bind(array('firstName' => 'John', 'lastName' => 'Smith'));

        $author = new Author();
        $author->firstName = 'John';
        $author->setLastName('Smith');

        $this->assertEquals($author, $form->getData());
    }

    private function createMockFactory(array $methods = array())
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormFactory')
            ->setMethods($methods)
            ->setConstructorArgs(array(array($this->extension1, $this->extension2)))
            ->getMock();
    }
}
