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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Form\SubmitButtonBuilder;

class FormBuilderTest extends TestCase
{
    private $factory;
    private $builder;

    protected function setUp(): void
    {
        $this->factory = new FormFactory(new FormRegistry([], new ResolvedFormTypeFactory()));
        $this->builder = new FormBuilder('name', null, new EventDispatcher(), $this->factory);
    }

    /**
     * Changing the name is not allowed, otherwise the name and property path
     * are not synchronized anymore.
     *
     * @see FormType::buildForm()
     */
    public function testNoSetName()
    {
        $this->assertFalse(method_exists($this->builder, 'setName'));
    }

    public function testAddWithGuessFluent()
    {
        $rootFormBuilder = new FormBuilder('name', 'stdClass', new EventDispatcher(), $this->factory);
        $childFormBuilder = $rootFormBuilder->add('foo');
        $this->assertSame($childFormBuilder, $rootFormBuilder);
    }

    public function testAddIsFluent()
    {
        $builder = $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType', ['bar' => 'baz']);
        $this->assertSame($builder, $this->builder);
    }

    public function testAdd()
    {
        $this->assertFalse($this->builder->has('foo'));
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->assertTrue($this->builder->has('foo'));
    }

    public function testAddIntegerName()
    {
        $this->assertFalse($this->builder->has(0));
        $this->builder->add(0, 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->assertTrue($this->builder->has(0));
    }

    public function testAll()
    {
        $this->assertCount(0, $this->builder->all());
        $this->assertFalse($this->builder->has('foo'));

        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $children = $this->builder->all();

        $this->assertTrue($this->builder->has('foo'));
        $this->assertCount(1, $children);
        $this->assertArrayHasKey('foo', $children);
    }

    /*
     * https://github.com/symfony/symfony/issues/4693
     */
    public function testMaintainOrderOfLazyAndExplicitChildren()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->builder->add(new FormBuilder('bar', null, new EventDispatcher(), $this->factory));
        $this->builder->add('baz', 'Symfony\Component\Form\Extension\Core\Type\TextType');

        $children = $this->builder->all();

        $this->assertSame(['foo', 'bar', 'baz'], array_keys($children));
    }

    public function testRemove()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->builder->remove('foo');
        $this->assertFalse($this->builder->has('foo'));
    }

    public function testRemoveUnknown()
    {
        $this->builder->remove('foo');
        $this->assertFalse($this->builder->has('foo'));
    }

    // https://github.com/symfony/symfony/pull/4826
    public function testRemoveAndGetForm()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->builder->remove('foo');
        $form = $this->builder->getForm();
        $this->assertInstanceOf(Form::class, $form);
    }

    public function testCreateNoTypeNo()
    {
        $builder = $this->builder->create('foo');

        $this->assertInstanceOf(TextType::class, $builder->getType()->getInnerType());
    }

    public function testAddButton()
    {
        $this->builder->add(new ButtonBuilder('reset'));
        $this->builder->add(new SubmitButtonBuilder('submit'));

        $this->assertCount(2, $this->builder->all());
    }

    public function testGetUnknown()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The child with the name "foo" does not exist.');

        $this->builder->get('foo');
    }

    public function testGetExplicitType()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $builder = $this->builder->get('foo');

        $this->assertNotSame($builder, $this->builder);
    }

    public function testGetGuessedType()
    {
        $rootFormBuilder = new FormBuilder('name', 'stdClass', new EventDispatcher(), $this->factory);
        $rootFormBuilder->add('foo');
        $fooBuilder = $rootFormBuilder->get('foo');

        $this->assertNotSame($fooBuilder, $rootFormBuilder);
    }

    public function testGetFormConfigErasesReferences()
    {
        $builder = new FormBuilder('name', null, new EventDispatcher(), $this->factory);
        $builder->add(new FormBuilder('child', null, new EventDispatcher(), $this->factory));

        $config = $builder->getFormConfig();
        $reflClass = new \ReflectionClass($config);
        $children = $reflClass->getProperty('children');
        $unresolvedChildren = $reflClass->getProperty('unresolvedChildren');

        $this->assertEmpty($children->getValue($config));
        $this->assertEmpty($unresolvedChildren->getValue($config));
    }

    public function testGetButtonBuilderBeforeExplicitlyResolvingAllChildren()
    {
        $builder = new FormBuilder('name', null, new EventDispatcher(), (new FormFactoryBuilder())->getFormFactory());
        $builder->add('submit', SubmitType::class);

        $this->assertInstanceOf(ButtonBuilder::class, $builder->get('submit'));
    }
}
