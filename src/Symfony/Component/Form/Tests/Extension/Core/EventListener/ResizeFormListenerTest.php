<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSetDataEvent;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\DataMapper\DataMapper;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormFactoryBuilder;
use Symfony\Component\Form\FormFactoryInterface;

class ResizeFormListenerTest extends TestCase
{
    private FormFactoryInterface $factory;
    private FormBuilderInterface $builder;

    protected function setUp(): void
    {
        $this->factory = (new FormFactoryBuilder())->getFormFactory();
        $this->builder = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new DataMapper());
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, null, new EventDispatcher(), $this->factory);
    }

    public function testPostSetDataResizesForm(): void
    {
        $this->builder->add($this->getBuilder('0'));
        $this->builder->add($this->getBuilder('1'));
        $this->builder->addEventSubscriber(new ResizeFormListener(TextType::class, ['attr' => ['maxlength' => 10]], false, false));

        $form = $this->builder->getForm();

        $this->assertTrue($form->has('0'));

        // initialize the form
        $form->setData([1 => 'string', 2 => 'string']);

        $this->assertFalse($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertTrue($form->has('2'));

        $this->assertSame('string', $form->get('1')->getData());
        $this->assertSame('string', $form->get('2')->getData());
    }

    public function testPostSetDataRequiresArrayOrTraversable(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $data = 'no array or traversable';
        $event = new PostSetDataEvent($this->builder->getForm(), $data);
        $listener = new ResizeFormListener(TextType::class, [], false, false);
        $listener->postSetData($event);
    }

    public function testPostSetDataDealsWithNullData(): void
    {
        $data = null;
        $event = new PostSetDataEvent($this->builder->getForm(), $data);
        $listener = new ResizeFormListener(TextType::class, [], false, false);
        $listener->postSetData($event);

        $this->assertSame(0, $this->builder->count());
    }

    public function testPreSubmitResizesUpIfAllowAdd(): void
    {
        $this->builder->add($this->getBuilder('0'));
        $this->builder->addEventSubscriber(new ResizeFormListener(TextType::class, ['attr' => ['maxlength' => 10]], true, false));

        $form = $this->builder->getForm();

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));

        $form->submit([0 => 'string', 1 => 'string']);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
    }

    public function testPreSubmitResizesDownIfAllowDelete(): void
    {
        $this->builder->add($this->getBuilder('0'));
        $this->builder->add($this->getBuilder('1'));
        $this->builder->addEventSubscriber(new ResizeFormListener(TextType::class, [], false, true));

        $form = $this->builder->getForm();
        // initialize the form
        $form->setData([0 => 'string', 1 => 'string']);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));

        $form->submit([0 => 'string']);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
    }

    // fix for https://github.com/symfony/symfony/pull/493
    public function testPreSubmitRemovesZeroKeys(): void
    {
        $this->builder->add($this->getBuilder('0'));

        $data = [];
        $form = $this->builder->getForm();
        $event = new FormEvent($form, $data);
        $listener = new ResizeFormListener(TextType::class, [], false, true);
        $listener->preSubmit($event);

        $this->assertFalse($form->has('0'));
    }

    public function testPreSubmitDoesNothingIfNotAllowAddNorAllowDelete(): void
    {
        $this->builder->add($this->getBuilder('0'));
        $this->builder->add($this->getBuilder('1'));

        $data = [0 => 'string', 2 => 'string'];
        $form = $this->builder->getForm();
        $event = new FormEvent($form, $data);
        $listener = new ResizeFormListener(TextType::class, [], false, false);
        $listener->preSubmit($event);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertFalse($form->has('2'));
    }

    public function testPreSubmitDealsWithNoArrayOrTraversable(): void
    {
        $data = 'no array or traversable';
        $form = $this->builder->getForm();
        $event = new FormEvent($form, $data);
        $listener = new ResizeFormListener(TextType::class, [], false, false);
        $listener->preSubmit($event);

        $this->assertFalse($form->has('1'));
    }

    public function testPreSubmitDealsWithNullData(): void
    {
        $this->builder->add($this->getBuilder('1'));

        $data = null;
        $form = $this->builder->getForm();
        $event = new FormEvent($form, $data);
        $listener = new ResizeFormListener(TextType::class, [], false, true);
        $listener->preSubmit($event);

        $this->assertFalse($form->has('1'));
    }

    // fixes https://github.com/symfony/symfony/pull/40
    public function testPreSubmitDealsWithEmptyData(): void
    {
        $this->builder->add($this->getBuilder('1'));

        $data = '';
        $form = $this->builder->getForm();
        $event = new FormEvent($form, $data);
        $listener = new ResizeFormListener(TextType::class, [], false, true);
        $listener->preSubmit($event);

        $this->assertFalse($form->has('1'));
    }

    public function testOnSubmitNormDataRemovesEntriesMissingInTheFormIfAllowDelete(): void
    {
        $this->builder->add($this->getBuilder('1'));

        $data = [0 => 'first', 1 => 'second', 2 => 'third'];
        $form = $this->builder->getForm();
        $event = new FormEvent($form, $data);
        $listener = new ResizeFormListener(TextType::class, [], false, true);
        $listener->onSubmit($event);

        $this->assertEquals([1 => 'second'], $event->getData());
    }

    public function testOnSubmitNormDataDoesNothingIfNotAllowDelete(): void
    {
        $this->builder->add($this->getBuilder('1'));

        $data = [0 => 'first', 1 => 'second', 2 => 'third'];
        $form = $this->builder->getForm();
        $event = new FormEvent($form, $data);
        $listener = new ResizeFormListener(TextType::class, [], false, false);
        $listener->onSubmit($event);

        $this->assertEquals($data, $event->getData());
    }

    public function testOnSubmitNormDataRequiresArrayOrTraversable(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $data = 'no array or traversable';
        $event = new FormEvent($this->builder->getForm(), $data);
        $listener = new ResizeFormListener(TextType::class, [], false, false);
        $listener->onSubmit($event);
    }

    public function testOnSubmitNormDataDealsWithNullData(): void
    {
        $this->builder->add($this->getBuilder('1'));

        $data = null;
        $event = new FormEvent($this->builder->getForm(), $data);
        $listener = new ResizeFormListener(TextType::class, [], false, true);
        $listener->onSubmit($event);

        $this->assertEquals([], $event->getData());
    }

    public function testOnSubmitDealsWithObjectBackedIteratorAggregate(): void
    {
        $this->builder->add($this->getBuilder('1'));

        $data = new \ArrayObject([0 => 'first', 1 => 'second', 2 => 'third']);
        $event = new FormEvent($this->builder->getForm(), $data);
        $listener = new ResizeFormListener(TextType::class, [], false, true);
        $listener->onSubmit($event);

        $this->assertArrayNotHasKey(0, $event->getData());
        $this->assertArrayNotHasKey(2, $event->getData());
    }

    public function testOnSubmitDealsWithDoctrineCollection(): void
    {
        $this->builder->add($this->getBuilder('1'));

        $data = new ArrayCollection([0 => 'first', 1 => 'second', 2 => 'third']);
        $event = new FormEvent($this->builder->getForm(), $data);
        $listener = new ResizeFormListener(TextType::class, [], false, true);
        $listener->onSubmit($event);

        $this->assertArrayNotHasKey(0, $event->getData());
        $this->assertArrayNotHasKey(2, $event->getData());
    }

    public function testKeepAsListWorksWithTraversableArrayAccess(): void
    {
        $this->builder->add($this->getBuilder('1'));

        $data = new \ArrayIterator([0 => 'first', 1 => 'second', 2 => 'third']);
        $event = new FormEvent($this->builder->getForm(), $data);
        $listener = new ResizeFormListener(TextType::class, keepAsList: true);
        $listener->onSubmit($event);

        $this->assertCount(1, $event->getData());
        $this->assertArrayHasKey(0, $event->getData());
    }

    public function testOnSubmitDeleteEmptyNotCompoundEntriesIfAllowDelete(): void
    {
        $this->builder->setData(['0' => 'first', '1' => 'second']);
        $this->builder->add($this->getBuilder('0'));
        $this->builder->add($this->getBuilder('1'));
        $this->builder->addEventSubscriber(new ResizeFormListener(TextType::class, [], false, true, true));

        $form = $this->builder->getForm();

        $form->submit([0 => 'first', 1 => '']);

        $this->assertEquals([0 => 'first'], $form->getData());
    }

    public function testOnSubmitDeleteEmptyCompoundEntriesIfAllowDelete(): void
    {
        $this->builder->setData(['0' => ['name' => 'John'], '1' => ['name' => 'Jane']]);
        $this->builder->add('0', NestedType::class);
        $this->builder->add('1', NestedType::class);
        $callback = static fn ($data) => empty($data['name']);
        $this->builder->addEventSubscriber(new ResizeFormListener(NestedType::class, [], false, true, $callback));

        $form = $this->builder->getForm();
        $form->submit(['0' => ['name' => 'John'], '1' => ['name' => '']]);

        $this->assertEquals(['0' => ['name' => 'John']], $form->getData());
    }
}

class NestedType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name');
    }
}
