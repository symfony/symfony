<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\AutoLabel\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Extension\AutoLabel\AutoLabelExtension;

class FormTypeAutoLabelExtensionTest_ChildType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('name', 'text');
    }

    public function getName()
    {
        return 'auto_label_test';
    }
}

class FormTypeAutoLabelExtensionTest extends TypeTestCase
{
    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), array(
            new AutoLabelExtension('%fullname%.%name%.%type%'),
        ));
    }

    public function testDefaultLabelGeneration()
    {
        $view = $this->factory->createNamed('firstname', 'text')->createView();
        $this->assertEquals('firstname.firstname.text', $view->vars['label']);
    }

    public function testFullnameGeneration()
    {
        $view = $this->factory
            ->createBuilder('form')
            ->add('form', 'form', array('auto_label' => '%fullname%'))
            ->getForm()
            ->createView()
        ;

        $this->assertEquals('form_form', $view['form']->vars['label']);
    }

    public function testForcedGeneration()
    {
        $view = $this->factory->createNamed('firstname', 'text', null, array('auto_label' => '%fullname%'))->createView();
        $this->assertEquals('firstname', $view->vars['label']);
    }

    public function testOverridenLabelGeneration()
    {
        $view = $this->factory->createNamed('firstname', 'text', null, array('auto_label' => 'foo.%name%'))->createView();
        $this->assertEquals('foo.firstname', $view->vars['label']);
    }

    public function testInheritedOption()
    {
        $builder = $this->factory->createNamedBuilder('firstname', 'form', array(
            'auto_label' => '%type%.%fullname%'
        ));

        $view = $builder->add('firstname', 'text')->getForm()->createView();
        $this->assertEquals('form.firstname', $view['firstname']->vars['label']);
    }
}
