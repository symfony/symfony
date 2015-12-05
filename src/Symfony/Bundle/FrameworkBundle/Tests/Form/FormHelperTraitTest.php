<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Form;

use Symfony\Bundle\FrameworkBundle\Form\FormHelperTrait;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class FormHelperTraitTest extends TestCase
{
    public function testCreateForm()
    {
        $form = $this->getMock(FormInterface::class);

        $formFactory = $this->getMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())->method('create')->willReturn($form);

        $helper = new DummyFormHelper($formFactory);

        $this->assertSame($form, $helper->createForm('foo'));
    }

    public function testCreateFormWithContainer()
    {
        $form = $this->getMock(FormInterface::class);

        $formFactory = $this->getMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())->method('create')->willReturn($form);

        $container = new Container();
        $container->set('form.factory', $formFactory);

        $helper = new DummyFormHelperWithContainer();
        $helper->setContainer($container);

        $this->assertSame($form, $helper->createForm('foo'));
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testCreateFormWithMissingDependencies()
    {
        $helper = new DummyFormHelperWithContainer();
        $helper->createForm('foo');
    }

    public function testCreateFormBuilder()
    {
        $formBuilder = $this->getMock(FormBuilderInterface::class);

        $formFactory = $this->getMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())->method('createBuilder')->willReturn($formBuilder);

        $helper = new DummyFormHelper($formFactory);

        $this->assertEquals($formBuilder, $helper->createFormBuilder('foo'));
    }

    public function testCreateFormBuilderWithContainer()
    {
        $formBuilder = $this->getMock(FormBuilderInterface::class);

        $formFactory = $this->getMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())->method('createBuilder')->willReturn($formBuilder);

        $container = new Container();
        $container->set('form.factory', $formFactory);

        $helper = new DummyFormHelperWithContainer();
        $helper->setContainer($container);

        $this->assertEquals($formBuilder, $helper->createFormBuilder('foo'));
    }

    /**
     * @expectedException \Symfony\Bundle\FrameworkBundle\Exception\LogicException
     */
    public function testCreateFormBuilderWithMissingDependencies()
    {
        $helper = new DummyFormHelperWithContainer();
        $helper->createFormBuilder('foo');
    }
}

class DummyFormHelper
{
    use FormHelperTrait {
        createForm as public;
        createFormBuilder as public;
    }

    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }
}

class DummyFormHelperWithContainer implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use FormHelperTrait {
        createForm as public;
        createFormBuilder as public;
    }
}
