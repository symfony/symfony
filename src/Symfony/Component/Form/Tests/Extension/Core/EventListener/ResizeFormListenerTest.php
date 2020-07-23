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
use Symfony\Component\Form\Extension\Core\DataMapper\PropertyPathMapper;
use Symfony\Component\Form\Extension\Core\EventListener\ResizeFormListener;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormFactoryBuilder;

class ResizeFormListenerTest extends TestCase
{
    private $factory;
    private $form;

    protected function setUp(): void
    {
        $this->factory = (new FormFactoryBuilder())->getFormFactory();
        $this->form = $this->getBuilder()
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
    }

    protected function tearDown(): void
    {
        $this->factory = null;
        $this->form = null;
    }

    protected function getBuilder($name = 'name')
    {
        return new FormBuilder($name, null, new EventDispatcher(), $this->factory);
    }

    protected function getForm($name = 'name')
    {
        return $this->getBuilder($name)->getForm();
    }

    public function testPreSetDataResizesForm()
    {
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $data = [1 => 'string', 2 => 'string'];
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener(TextType::class, ['attr' => ['maxlength' => 10]], false, false);
        $listener->preSetData($event);

        $this->assertFalse($this->form->has('0'));
        $this->assertTrue($this->form->has('1'));
        $this->assertTrue($this->form->has('2'));
    }

    public function testPreSetDataResizesFormWithIndexedName()
    {
        $this->form->add($this->getForm('my-id-0'));
        $this->form->add($this->getForm('my-id-1'));

        $data = ['my-id-2' => 'string', 'my-id-1' => 'string xy'];
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener(TextType::class, ['attr' => ['maxlength' => 10]], false, false);
        $listener->preSetData($event);

        $this->assertFalse($this->form->has('my-id-0'));
        $this->assertTrue($this->form->has('my-id-1'));
        $this->assertTrue($this->form->has('my-id-2'));
    }

    public function testPreSetDataRequiresArrayOrTraversable()
    {
        $this->expectException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $data = 'no array or traversable';
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, false);
        $listener->preSetData($event);
    }

    public function testPreSetDataDealsWithNullData()
    {
        $data = null;
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener(TextType::class, [], false, false);
        $listener->preSetData($event);

        $this->assertSame(0, $this->form->count());
    }

    public function testPreSubmitResizesUpIfAllowAdd()
    {
        $this->form->add($this->getForm('0'));

        $data = [0 => 'string', 1 => 'string'];
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener(TextType::class, ['attr' => ['maxlength' => 10]], true, false);
        $listener->preSubmit($event);

        $this->assertTrue($this->form->has('0'));
        $this->assertTrue($this->form->has('1'));
    }

    public function testPreSubmitResizesDownIfAllowDelete()
    {
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $data = [0 => 'string'];
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true);
        $listener->preSubmit($event);

        $this->assertTrue($this->form->has('0'));
        $this->assertFalse($this->form->has('1'));
    }

    // fix for https://github.com/symfony/symfony/pull/493
    public function testPreSubmitRemovesZeroKeys()
    {
        $this->form->add($this->getForm('0'));

        $data = [];
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true);
        $listener->preSubmit($event);

        $this->assertFalse($this->form->has('0'));
    }

    public function testPreSubmitDoesNothingIfNotAllowAddNorAllowDelete()
    {
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $data = [0 => 'string', 2 => 'string'];
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, false);
        $listener->preSubmit($event);

        $this->assertTrue($this->form->has('0'));
        $this->assertTrue($this->form->has('1'));
        $this->assertFalse($this->form->has('2'));
    }

    public function testPreSubmitDealsWithNoArrayOrTraversable()
    {
        $data = 'no array or traversable';
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, false);
        $listener->preSubmit($event);

        $this->assertFalse($this->form->has('1'));
    }

    public function testPreSubmitDealsWithNullData()
    {
        $this->form->add($this->getForm('1'));

        $data = null;
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true);
        $listener->preSubmit($event);

        $this->assertFalse($this->form->has('1'));
    }

    // fixes https://github.com/symfony/symfony/pull/40
    public function testPreSubmitDealsWithEmptyData()
    {
        $this->form->add($this->getForm('1'));

        $data = '';
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true);
        $listener->preSubmit($event);

        $this->assertFalse($this->form->has('1'));
    }

    public function testOnSubmitNormDataRemovesEntriesMissingInTheFormIfAllowDelete()
    {
        $this->form->add($this->getForm('1'));

        $data = [0 => 'first', 1 => 'second', 2 => 'third'];
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true);
        $listener->onSubmit($event);

        $this->assertEquals([1 => 'second'], $event->getData());
    }

    public function testOnSubmitNormDataRemovesEntriesMissingInTheFormIfAllowDeleteWithIndexedName()
    {
        $this->form->add($this->getForm('my-id-1'));

        $data = ['my-id-0' => 'first', 'my-id-1' => 'second', 'my-id-2' => 'third'];
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true);
        $listener->onSubmit($event);

        $this->assertEquals(['my-id-1' => 'second'], $event->getData());
    }

    public function testOnSubmitNormDataDoesNothingIfNotAllowDelete()
    {
        $this->form->add($this->getForm('1'));

        $data = [0 => 'first', 1 => 'second', 2 => 'third'];
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, false);
        $listener->onSubmit($event);

        $this->assertEquals($data, $event->getData());
    }

    public function testOnSubmitNormDataRequiresArrayOrTraversable()
    {
        $this->expectException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $data = 'no array or traversable';
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, false);
        $listener->onSubmit($event);
    }

    public function testOnSubmitNormDataDealsWithNullData()
    {
        $this->form->add($this->getForm('1'));

        $data = null;
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true);
        $listener->onSubmit($event);

        $this->assertEquals([], $event->getData());
    }

    public function testOnSubmitDealsWithObjectBackedIteratorAggregate()
    {
        $this->form->add($this->getForm('1'));

        $data = new \ArrayObject([0 => 'first', 1 => 'second', 2 => 'third']);
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true);
        $listener->onSubmit($event);

        $this->assertArrayNotHasKey(0, $event->getData());
        $this->assertArrayNotHasKey(2, $event->getData());
    }

    public function testOnSubmitDealsWithArrayBackedIteratorAggregate()
    {
        $this->form->add($this->getForm('1'));

        $data = new ArrayCollection([0 => 'first', 1 => 'second', 2 => 'third']);
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true);
        $listener->onSubmit($event);

        $this->assertArrayNotHasKey(0, $event->getData());
        $this->assertArrayNotHasKey(2, $event->getData());
    }

    public function testOnSubmitDeleteEmptyNotCompoundEntriesIfAllowDelete()
    {
        $this->form->setData(['0' => 'first', '1' => 'second']);
        $this->form->add($this->getForm('0'));
        $this->form->add($this->getForm('1'));

        $data = [0 => 'first', 1 => ''];
        foreach ($data as $child => $dat) {
            $this->form->get($child)->setData($dat);
        }
        $event = new FormEvent($this->form, $data);
        $listener = new ResizeFormListener('text', [], false, true, true);
        $listener->onSubmit($event);

        $this->assertEquals([0 => 'first'], $event->getData());
    }

    public function testOnSubmitDeleteEmptyCompoundEntriesIfAllowDelete()
    {
        $this->form->setData(['0' => ['name' => 'John'], '1' => ['name' => 'Jane']]);
        $form1 = $this->getBuilder('0')
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $form1->add($this->getForm('name'));
        $form2 = $this->getBuilder('1')
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $form2->add($this->getForm('name'));
        $this->form->add($form1);
        $this->form->add($form2);

        $data = ['0' => ['name' => 'John'], '1' => ['name' => '']];
        foreach ($data as $child => $dat) {
            $this->form->get($child)->setData($dat);
        }
        $event = new FormEvent($this->form, $data);
        $callback = function ($data) {
            return '' === $data['name'];
        };
        $listener = new ResizeFormListener('text', [], false, true, $callback);
        $listener->onSubmit($event);

        $this->assertEquals(['0' => ['name' => 'John']], $event->getData());
    }

    public function testOnSubmitDeleteEmptyCompoundEntriesIfAllowDeleteWithIndexedName()
    {
        $this->form->setData(['my-id-1' => ['name' => 'John'], 'my-id-2' => ['name' => 'Jane']]);
        $form1 = $this->getBuilder('my-id-1')
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $form1->add($this->getForm('name'));
        $form2 = $this->getBuilder('my-id-2')
            ->setCompound(true)
            ->setDataMapper(new PropertyPathMapper())
            ->getForm();
        $form2->add($this->getForm('name'));
        $this->form->add($form1);
        $this->form->add($form2);

        $data = ['my-id-1' => ['name' => 'John'], 'my-id-2' => ['name' => '']];
        foreach ($data as $child => $dat) {
            $this->form->get($child)->setData($dat);
        }
        $event = new FormEvent($this->form, $data);
        $callback = function ($data) {
            return '' === $data['name'];
        };
        $listener = new ResizeFormListener('text', [], false, true, $callback);
        $listener->onSubmit($event);

        $this->assertEquals(['my-id-1' => ['name' => 'John']], $event->getData());
    }

    public function testIndexedNameFeature()
    {
        $form = $this->factory->createNamedBuilder('root', FormType::class, ['items' => null])
            ->add('items', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'data' => ['foo'],
                'index_name' => 'id',
            ])
            ->getForm()
        ;

        $this->assertSame(['foo'], $form->get('items')->getData());
        $form->submit(['items' => ['foo', 'my-id-1' => 'foo', 'my-id-2' => 'bar']]);
        $this->assertSame(['foo', 'my-id-1' => 'foo', 'my-id-2' => 'bar'], $form->get('items')->getData());
    }

    public function testIndexedNameFeatureWithAllowDelete()
    {
        $form = $this->factory->createNamedBuilder('root', FormType::class, ['items' => null])
            ->add('items', CollectionType::class, [
                'entry_type' => TextType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'data' => ['foo'],
                'index_name' => 'id',
            ])
            ->getForm()
        ;

        $this->assertSame(['foo'], $form->get('items')->getData());
        $form->submit(['items' => ['my-id-1' => 'foo', 'my-id-2' => 'bar']]);
        $this->assertSame(['my-id-1' => 'foo', 'my-id-2' => 'bar'], $form->get('items')->getData());
    }

    public function testIndexedNameFeatureWithSimulatedArray()
    {
        $form = $this->factory->createNamedBuilder('root', FormType::class, ['items' => null])
            ->add('items', CollectionType::class, [
                'entry_type' => FooType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'data' => $data = [
                    ['id' => 'custom-id-1', 'foo' => 'bar'],
                    ['id' => 'custom-id-2', 'foo' => 'me'],
                    ['id' => 'custom-id-3', 'foo' => 'foo'],
                ],
                'index_name' => 'id',
            ])
            ->getForm()
        ;

        $this->assertSame($data, $form->get('items')->getData());
        $form->submit(['items' => [
            'custom-id-3' => ['foo' => 'foo 2'],
            'custom-id-1' => ['foo' => 'bar 2'],
        ]]);
        $this->assertSame([
            'custom-id-1' => ['id' => 'custom-id-1', 'foo' => 'bar 2'],
            'custom-id-3' => ['id' => 'custom-id-3', 'foo' => 'foo 2'],
        ], $form->get('items')->getData());
    }
}
