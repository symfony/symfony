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
use Symfony\Component\Form\Exception\UnexpectedTypeException;
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
        self::assertFalse(method_exists($this->builder, 'setName'));
    }

    public function testAddNameNoStringAndNoInteger()
    {
        self::expectException(UnexpectedTypeException::class);
        $this->builder->add(true);
    }

    public function testAddWithGuessFluent()
    {
        $rootFormBuilder = new FormBuilder('name', 'stdClass', new EventDispatcher(), $this->factory);
        $childFormBuilder = $rootFormBuilder->add('foo');
        self::assertSame($childFormBuilder, $rootFormBuilder);
    }

    public function testAddIsFluent()
    {
        $builder = $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType', ['bar' => 'baz']);
        self::assertSame($builder, $this->builder);
    }

    public function testAdd()
    {
        self::assertFalse($this->builder->has('foo'));
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        self::assertTrue($this->builder->has('foo'));
    }

    public function testAddIntegerName()
    {
        self::assertFalse($this->builder->has(0));
        $this->builder->add(0, 'Symfony\Component\Form\Extension\Core\Type\TextType');
        self::assertTrue($this->builder->has(0));
    }

    public function testAll()
    {
        self::assertCount(0, $this->builder->all());
        self::assertFalse($this->builder->has('foo'));

        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $children = $this->builder->all();

        self::assertTrue($this->builder->has('foo'));
        self::assertCount(1, $children);
        self::assertArrayHasKey('foo', $children);
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

        self::assertSame(['foo', 'bar', 'baz'], array_keys($children));
    }

    public function testRemove()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->builder->remove('foo');
        self::assertFalse($this->builder->has('foo'));
    }

    public function testRemoveUnknown()
    {
        $this->builder->remove('foo');
        self::assertFalse($this->builder->has('foo'));
    }

    // https://github.com/symfony/symfony/pull/4826
    public function testRemoveAndGetForm()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->builder->remove('foo');
        $form = $this->builder->getForm();
        self::assertInstanceOf(Form::class, $form);
    }

    public function testCreateNoTypeNo()
    {
        $builder = $this->builder->create('foo');

        self::assertInstanceOf(TextType::class, $builder->getType()->getInnerType());
    }

    public function testAddButton()
    {
        $this->builder->add(new ButtonBuilder('reset'));
        $this->builder->add(new SubmitButtonBuilder('submit'));

        self::assertCount(2, $this->builder->all());
    }

    public function testGetUnknown()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The child with the name "foo" does not exist.');

        $this->builder->get('foo');
    }

    public function testGetExplicitType()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $builder = $this->builder->get('foo');

        self::assertNotSame($builder, $this->builder);
    }

    public function testGetGuessedType()
    {
        $rootFormBuilder = new FormBuilder('name', 'stdClass', new EventDispatcher(), $this->factory);
        $rootFormBuilder->add('foo');
        $fooBuilder = $rootFormBuilder->get('foo');

        self::assertNotSame($fooBuilder, $rootFormBuilder);
    }

    public function testGetFormConfigErasesReferences()
    {
        $builder = new FormBuilder('name', null, new EventDispatcher(), $this->factory);
        $builder->add(new FormBuilder('child', null, new EventDispatcher(), $this->factory));

        $config = $builder->getFormConfig();
        $reflClass = new \ReflectionClass($config);
        $children = $reflClass->getProperty('children');
        $unresolvedChildren = $reflClass->getProperty('unresolvedChildren');

        $children->setAccessible(true);
        $unresolvedChildren->setAccessible(true);

        self::assertEmpty($children->getValue($config));
        self::assertEmpty($unresolvedChildren->getValue($config));
    }

    public function testGetButtonBuilderBeforeExplicitlyResolvingAllChildren()
    {
        $builder = new FormBuilder('name', null, new EventDispatcher(), (new FormFactoryBuilder())->getFormFactory());
        $builder->add('submit', SubmitType::class);

        self::assertInstanceOf(ButtonBuilder::class, $builder->get('submit'));
    }
}
