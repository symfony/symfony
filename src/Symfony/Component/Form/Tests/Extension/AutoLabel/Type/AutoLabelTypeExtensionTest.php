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
use Symfony\Component\Form\FormError;
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
            new AutoLabelExtension('form.%type%.label.%name%'),
        ));
    }

    public function testDefaultLabelGeneration()
    {
        $view = $this->factory->createNamed('firstname', 'text')->createView();
        $this->assertEquals('form.text.label.firstname', $view->vars['label']);
    }
}
