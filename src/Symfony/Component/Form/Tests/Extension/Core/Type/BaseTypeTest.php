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

use Symfony\Component\Form\Test\TypeTestCase;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class BaseTypeTest extends TypeTestCase
{
    const TESTED_TYPE = '';

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
        $view = $this->factory->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', $this->getTestedType())
            ->getForm()
            ->createView();

        $this->assertEquals('parent_child', $view['child']->vars['id']);
        $this->assertEquals('child', $view['child']->vars['name']);
        $this->assertEquals('parent[child]', $view['child']->vars['full_name']);
    }

    public function testPassIdAndNameToViewWithGrandParent()
    {
        $builder = $this->factory->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', FormTypeTest::TESTED_TYPE);
        $builder->get('child')->add('grand_child', $this->getTestedType());
        $view = $builder->getForm()->createView();

        $this->assertEquals('parent_child_grand_child', $view['child']['grand_child']->vars['id']);
        $this->assertEquals('grand_child', $view['child']['grand_child']->vars['name']);
        $this->assertEquals('parent[child][grand_child]', $view['child']['grand_child']->vars['full_name']);
    }

    public function testPassTranslationDomainToView()
    {
        $view = $this->factory->create($this->getTestedType(), null, array(
            'translation_domain' => 'domain',
        ))
            ->createView();

        $this->assertSame('domain', $view->vars['translation_domain']);
    }

    public function testInheritTranslationDomainFromParent()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, array(
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
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, array(
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
        $view = $this->factory->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', $this->getTestedType())
            ->getForm()
            ->createView();

        $this->assertNull($view['child']->vars['translation_domain']);
    }

    public function testPassTranslationParametersToView()
    {
        $view = $this->factory->create($this->getTestedType(), null, array(
            'translation_parameters' => array('%param%' => 'value'),
        ))
            ->createView();

        $this->assertSame(array('%param%' => 'value'), $view->vars['translation_parameters']);
    }

    public function testInheritTranslationParametersFromParent()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, array(
                'translation_parameters' => array('%param%' => 'value'),
            ))
            ->add('child', $this->getTestedType())
            ->getForm()
            ->createView();

        $this->assertEquals(array('%param%' => 'value'), $view['child']->vars['translation_parameters']);
    }

    public function testPreferOwnTranslationParameters()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, array(
                'translation_parameters' => array('%parent_param%' => 'parent_value', '%override_param%' => 'parent_override_value'),
            ))
            ->add('child', $this->getTestedType(), array(
                'translation_parameters' => array('%override_param%' => 'child_value'),
            ))
            ->getForm()
            ->createView();

        $this->assertEquals(array('%parent_param%' => 'parent_value', '%override_param%' => 'child_value'), $view['child']->vars['translation_parameters']);
    }

    public function testDefaultTranslationParameters()
    {
        $view = $this->factory->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', $this->getTestedType())
            ->getForm()
            ->createView();

        $this->assertEquals(array(), $view['child']->vars['translation_parameters']);
    }

    public function testPassLabelToView()
    {
        $view = $this->factory->createNamed('__test___field', $this->getTestedType(), null, array('label' => 'My label'))
            ->createView();

        $this->assertSame('My label', $view->vars['label']);
    }

    public function testPassMultipartFalseToView()
    {
        $view = $this->factory->create($this->getTestedType())
            ->createView();

        $this->assertFalse($view->vars['multipart']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        $form = $this->factory->create($this->getTestedType());
        $form->submit(null);

        $this->assertSame($expected, $form->getData());
        $this->assertSame($norm, $form->getNormData());
        $this->assertSame($view, $form->getViewData());
    }

    protected function getTestedType()
    {
        return static::TESTED_TYPE;
    }
}
