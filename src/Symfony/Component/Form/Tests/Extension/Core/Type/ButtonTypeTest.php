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

use Symfony\Component\Form\Button;
use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Type\FormType;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\ButtonType';

    public function testCreateButtonInstances()
    {
        $this->assertInstanceOf(Button::class, $this->factory->create(static::TESTED_TYPE));
    }

    /**
     * @param string $emptyData
     * @param null   $expectedData
     */
    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'empty', $expectedData = null)
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Buttons do not support empty data.');
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }

    public function testFormAttrOnRoot()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormType::class, null, [
                'form_attr' => true,
            ])
            ->add('child1', $this->getTestedType())
            ->add('child2', $this->getTestedType())
            ->getForm()
            ->createView();
        $this->assertArrayNotHasKey('form', $view->vars['attr']);
        $this->assertSame($view->vars['id'], $view['child1']->vars['attr']['form']);
        $this->assertSame($view->vars['id'], $view['child2']->vars['attr']['form']);
    }

    public function testFormAttrOnChild()
    {
        $view = $this->factory
            ->createNamedBuilder('parent')
            ->add('child1', $this->getTestedType(), [
                'form_attr' => true,
            ])
            ->add('child2', $this->getTestedType())
            ->getForm()
            ->createView();
        $this->assertArrayNotHasKey('form', $view->vars['attr']);
        $this->assertSame($view->vars['id'], $view['child1']->vars['attr']['form']);
        $this->assertArrayNotHasKey('form', $view['child2']->vars['attr']);
    }

    public function testFormAttrAsBoolWithNoId()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('form_attr');
        $this->factory
            ->createNamedBuilder('', FormType::class, null, [
                'form_attr' => true,
            ])
            ->add('child1', $this->getTestedType())
            ->add('child2', $this->getTestedType())
            ->getForm()
            ->createView();
    }

    public function testFormAttrAsStringWithNoId()
    {
        $stringId = 'custom-identifier';
        $view = $this->factory
            ->createNamedBuilder('', FormType::class, null, [
                'form_attr' => $stringId,
            ])
            ->add('child1', $this->getTestedType())
            ->add('child2', $this->getTestedType())
            ->getForm()
            ->createView();
        $this->assertArrayNotHasKey('form', $view->vars['attr']);
        $this->assertSame($stringId, $view->vars['id']);
        $this->assertSame($view->vars['id'], $view['child1']->vars['attr']['form']);
        $this->assertSame($view->vars['id'], $view['child2']->vars['attr']['form']);
    }
}
