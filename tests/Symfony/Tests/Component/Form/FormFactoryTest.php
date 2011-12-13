<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Tests\Component\Form\Fixtures\TestExtension;
use Symfony\Tests\Component\Form\Fixtures\FooType;
use Symfony\Tests\Component\Form\Fixtures\FooTypeBarExtension;
use Symfony\Tests\Component\Form\Fixtures\FooTypeBazExtension;

class FormFactoryTest extends \PHPUnit_Framework_TestCase
{
    private $extension1;

    private $extension2;

    private $guesser1;

    private $guesser2;

    private $factory;

    protected function setUp()
    {
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

        $builder = $this->factory->createNamedBuilder('foo', 'bar');

        $this->assertTrue($builder instanceof FormBuilder);
        $this->assertEquals('bar', $builder->getName());
    }

    public function testCreateNamedBuilderCallsBuildFormMethods()
    {
        $type = new FooType();
        $ext1 = new FooTypeBarExtension();
        $ext2 = new FooTypeBazExtension();

        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);
        $this->extension2->addType($type);

        $builder = $this->factory->createNamedBuilder('foo', 'bar');

        $this->assertTrue($builder->hasAttribute('foo'));
        $this->assertTrue($builder->hasAttribute('bar'));
        $this->assertTrue($builder->hasAttribute('baz'));
    }

    public function testCreateNamedBuilderFillsDataOption()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $builder = $this->factory->createNamedBuilder('foo', 'bar', 'xyz');

        // see FooType::buildForm()
        $this->assertEquals('xyz', $builder->getAttribute('data_option'));
    }

    public function testCreateNamedBuilderDoesNotOverrideExistingDataOption()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $builder = $this->factory->createNamedBuilder('foo', 'bar', 'xyz', array(
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
        $type->expects($this->any())
            ->method('getAllowedOptionValues')
            ->will($this->returnValue(array()));
        $type->expects($this->any())
            ->method('getDefaultOptions')
            ->will($this->returnValue(array(
                'required' => false,
                'max_length' => null,
            )));

        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('foo', 'bar');
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
        $type->expects($this->any())
            ->method('getAllowedOptionValues')
            ->will($this->returnValue(array()));
        $type->expects($this->any())
            ->method('getDefaultOptions')
            ->will($this->returnValue(array(
                'data' => null,
                'max_length' => null,
            )));

        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('foo', 'bar');
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
        $type->expects($this->any())
            ->method('getAllowedOptionValues')
            ->will($this->returnValue(array()));
        $type->expects($this->any())
            ->method('getDefaultOptions')
            ->will($this->returnValue(array(
                'data' => null,
                'required' => false,
            )));

        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('foo', 'bar');
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
            ->method('getAllowedOptionValues')
            ->will($this->returnValue(array()));
        $type->expects($this->any())
            ->method('getDefaultOptions')
            ->will($this->returnValue(array(
                'data' => null,
                'required' => false,
                'max_length' => null,
            )));
        $type->expects($this->any())
            ->method('createBuilder')
            ->will($this->returnValue(null));

        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('foo', 'bar');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\CreationException
     */
    public function testCreateNamedBuilderExpectsOptionsToExist()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('foo', 'bar', null, array(
            'invalid' => 'xyz',
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\CreationException
     */
    public function testCreateNamedBuilderExpectsOptionsToBeInValidRange()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $this->factory->createNamedBuilder('foo', 'bar', null, array(
            'a_or_b' => 'c',
        ));
    }

    public function testCreateNamedBuilderAllowsExtensionsToExtendAllowedOptionValues()
    {
        $type = new FooType();
        $this->extension1->addType($type);
        $this->extension1->addTypeExtension(new FooTypeBarExtension());

        // no exception this time
        $this->factory->createNamedBuilder('foo', 'bar', null, array(
            'a_or_b' => 'c',
        ));
    }

    public function testCreateNamedBuilderAddsTypeInstances()
    {
        $type = new FooType();
        $this->assertFalse($this->factory->hasType('foo'));

        $builder = $this->factory->createNamedBuilder($type, 'bar');

        $this->assertTrue($builder instanceof FormBuilder);
        $this->assertTrue($this->factory->hasType('foo'));
    }

    /**
     * @expectedException        Symfony\Component\Form\Exception\UnexpectedTypeException
     * @expectedExceptionMessage Expected argument of type "string or Symfony\Component\Form\FormTypeInterface", "stdClass" given
     */
    public function testCreateNamedBuilderThrowsUnderstandableException()
    {
        $this->factory->createNamedBuilder(new \StdClass, 'name');
    }

    public function testCreateUsesTypeNameAsName()
    {
        $type = new FooType();
        $this->extension1->addType($type);

        $builder = $this->factory->createBuilder('foo');

        $this->assertEquals('foo', $builder->getName());
    }

    public function testCreateBuilderForPropertyCreatesFieldWithHighestConfidence()
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
            ->with('password', 'firstName', null, array('max_length' => 7))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty('Application\Author', 'firstName');

        $this->assertEquals('builderInstance', $builder);
    }

    public function testCreateBuilderCreatesTextFieldIfNoGuess()
    {
        $this->guesser1->expects($this->once())
                ->method('guessType')
                ->with('Application\Author', 'firstName')
                ->will($this->returnValue(null));

        $factory = $this->createMockFactory(array('createNamedBuilder'));

        $factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('text', 'firstName')
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
            ->with('text', 'firstName', null, array('max_length' => 11))
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
            ->with('text', 'firstName', null, array('max_length' => 20))
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
            ->with('text', 'firstName', null, array('required' => false))
            ->will($this->returnValue('builderInstance'));

        $builder = $factory->createBuilderForProperty(
            'Application\Author',
            'firstName'
        );

        $this->assertEquals('builderInstance', $builder);
    }

    private function createMockFactory(array $methods = array())
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormFactory')
            ->setMethods($methods)
            ->setConstructorArgs(array(array($this->extension1, $this->extension2)))
            ->getMock();
    }
}
