<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class BaseTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    public function testPassDisabledAsOption()
    {
        $form = $this->factory->create($this->getTestedType(), null, array('disabled' => true));

        $this->assertTrue($form->isDisabled());
    }

    public function testPassIdAndNameToView()
    {
        $view = $this->factory->createNamed('name', $this->getTestedType())
            ->createView();

        $this->assertEquals('name', $view->vars['id']);
        $this->assertEquals('name', $view->vars['name']);
        $this->assertEquals('name', $view->vars['full_name']);
    }

    public function testStripLeadingUnderscoresAndDigitsFromId()
    {
        $view = $this->factory->createNamed('_09name', $this->getTestedType())
            ->createView();

        $this->assertEquals('name', $view->vars['id']);
        $this->assertEquals('_09name', $view->vars['name']);
        $this->assertEquals('_09name', $view->vars['full_name']);
    }

    public function testPassIdAndNameToViewWithParent()
    {
        $view = $this->factory->createNamedBuilder('parent', 'form')
            ->add('child', $this->getTestedType())
            ->getForm()
            ->createView();

        $this->assertEquals('parent_child', $view['child']->vars['id']);
        $this->assertEquals('child', $view['child']->vars['name']);
        $this->assertEquals('parent[child]', $view['child']->vars['full_name']);
    }

    public function testPassIdAndNameToViewWithGrandParent()
    {
        $builder = $this->factory->createNamedBuilder('parent', 'form')
            ->add('child', 'form');
        $builder->get('child')->add('grand_child', $this->getTestedType());
        $view = $builder->getForm()->createView();

        $this->assertEquals('parent_child_grand_child', $view['child']['grand_child']->vars['id']);
        $this->assertEquals('grand_child', $view['child']['grand_child']->vars['name']);
        $this->assertEquals('parent[child][grand_child]', $view['child']['grand_child']->vars['full_name']);
    }

    public function testPassTranslationDomainToView()
    {
        $form = $this->factory->create($this->getTestedType(), null, array(
            'translation_domain' => 'domain',
        ));
        $view = $form->createView();

        $this->assertSame('domain', $view->vars['translation_domain']);
    }

    public function testInheritTranslationDomainFromParent()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', 'form', null, array(
                'translation_domain' => 'domain',
            ))
            ->add('child', $this->getTestedType())
            ->getForm()
            ->createView();

        $this->assertEquals('domain', $view['child']->vars['translation_domain']);
    }

    public function testPreferOwnTranslationDomain()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', 'form', null, array(
                'translation_domain' => 'parent_domain',
            ))
            ->add('child', $this->getTestedType(), array(
                'translation_domain' => 'domain',
            ))
            ->getForm()
            ->createView();

        $this->assertEquals('domain', $view['child']->vars['translation_domain']);
    }

    public function testDefaultTranslationDomain()
    {
        $view = $this->factory->createNamedBuilder('parent', 'form')
            ->add('child', $this->getTestedType())
            ->getForm()
            ->createView();

        $this->assertEquals('messages', $view['child']->vars['translation_domain']);
    }

    public function testPassLabelToView()
    {
        $form = $this->factory->createNamed('__test___field', $this->getTestedType(), null, array('label' => 'My label'));
        $view = $form->createView();

        $this->assertSame('My label', $view->vars['label']);
    }

    public function testPassMultipartFalseToView()
    {
        $form = $this->factory->create($this->getTestedType());
        $view = $form->createView();

        $this->assertFalse($view->vars['multipart']);
    }
    
    abstract protected function getTestedType();
}
