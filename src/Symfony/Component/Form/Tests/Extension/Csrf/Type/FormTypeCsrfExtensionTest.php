<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Csrf\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Tests\Extension\Core\Type\TypeTestCase;

class FormTypeCsrfExtensionTest_ChildType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // The form needs a child in order to trigger CSRF protection by
        // default
        $builder->add('name', 'text');
    }

    public function getName()
    {
        return 'csrf_collection_test';
    }
}

class FormTypeCsrfExtensionTest extends TypeTestCase
{
    protected $csrfProvider;

    protected function setUp()
    {
        $this->csrfProvider = $this->getMock('Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface');

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->csrfProvider = null;

        parent::tearDown();
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), array(
            new CsrfExtension($this->csrfProvider),
        ));
    }

    public function testCsrfProtectionByDefaultIfRootAndCompound()
    {
        $view = $this->factory
            ->create('form', null, array(
                'csrf_field_name' => 'csrf',
                'compound' => true,
            ))
            ->createView();

        $this->assertTrue(isset($view['csrf']));
    }

    public function testNoCsrfProtectionByDefaultIfCompoundButNotRoot()
    {
        $view = $this->factory
            ->createNamedBuilder('root', 'form')
            ->add($this->factory
                ->createNamedBuilder('form', 'form', null, array(
                    'csrf_field_name' => 'csrf',
                    'compound' => true,
                ))
            )
            ->getForm()
            ->get('form')
            ->createView();

        $this->assertFalse(isset($view['csrf']));
    }

    public function testNoCsrfProtectionByDefaultIfRootButNotCompound()
    {
        $view = $this->factory
            ->create('form', null, array(
                'csrf_field_name' => 'csrf',
                'compound' => false,
            ))
            ->createView();

        $this->assertFalse(isset($view['csrf']));
    }

    public function testCsrfProtectionCanBeDisabled()
    {
        $view = $this->factory
            ->create('form', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_protection' => false,
                'compound' => true,
            ))
            ->createView();

        $this->assertFalse(isset($view['csrf']));
    }

    public function testGenerateCsrfToken()
    {
        $this->csrfProvider->expects($this->once())
            ->method('generateCsrfToken')
            ->with('%INTENTION%')
            ->will($this->returnValue('token'));

        $view = $this->factory
            ->create('form', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_provider' => $this->csrfProvider,
                'intention' => '%INTENTION%',
                'compound' => true,
            ))
            ->createView();

        $this->assertEquals('token', $view['csrf']->vars['value']);
    }

    public function provideBoolean()
    {
        return array(
            array(true),
            array(false),
        );
    }

    /**
     * @dataProvider provideBoolean
     */
    public function testValidateTokenOnBindIfRootAndCompound($valid)
    {
        $this->csrfProvider->expects($this->once())
            ->method('isCsrfTokenValid')
            ->with('%INTENTION%', 'token')
            ->will($this->returnValue($valid));

        $form = $this->factory
            ->createBuilder('form', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_provider' => $this->csrfProvider,
                'intention' => '%INTENTION%',
                'compound' => true,
            ))
            ->add('child', 'text')
            ->getForm();

        $form->bind(array(
            'child' => 'foobar',
            'csrf' => 'token',
        ));

        // Remove token from data
        $this->assertSame(array('child' => 'foobar'), $form->getData());

        // Validate accordingly
        $this->assertSame($valid, $form->isValid());
    }

    public function testFailIfRootAndCompoundAndTokenMissing()
    {
        $this->csrfProvider->expects($this->never())
            ->method('isCsrfTokenValid');

        $form = $this->factory
            ->createBuilder('form', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_provider' => $this->csrfProvider,
                'intention' => '%INTENTION%',
                'compound' => true,
            ))
            ->add('child', 'text')
            ->getForm();

        $form->bind(array(
            'child' => 'foobar',
            // token is missing
        ));

        // Remove token from data
        $this->assertSame(array('child' => 'foobar'), $form->getData());

        // Validate accordingly
        $this->assertFalse($form->isValid());
    }

    public function testDontValidateTokenIfCompoundButNoRoot()
    {
        $this->csrfProvider->expects($this->never())
            ->method('isCsrfTokenValid');

        $form = $this->factory
            ->createNamedBuilder('root', 'form')
            ->add($this->factory
                ->createNamedBuilder('form', 'form', null, array(
                    'csrf_field_name' => 'csrf',
                    'csrf_provider' => $this->csrfProvider,
                    'intention' => '%INTENTION%',
                    'compound' => true,
                ))
            )
            ->getForm()
            ->get('form');

        $form->bind(array(
            'child' => 'foobar',
            'csrf' => 'token',
        ));
    }

    public function testDontValidateTokenIfRootButNotCompound()
    {
        $this->csrfProvider->expects($this->never())
            ->method('isCsrfTokenValid');

        $form = $this->factory
            ->create('form', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_provider' => $this->csrfProvider,
                'intention' => '%INTENTION%',
                'compound' => false,
            ));

        $form->bind(array(
            'csrf' => 'token',
        ));
    }

    public function testNoCsrfProtectionOnPrototype()
    {
        $prototypeView = $this->factory
            ->create('collection', null, array(
                'type' => new FormTypeCsrfExtensionTest_ChildType(),
                'options' => array(
                    'csrf_field_name' => 'csrf',
                ),
                'prototype' => true,
                'allow_add' => true,
            ))
            ->createView()
            ->vars['prototype'];

        $this->assertFalse(isset($prototypeView['csrf']));
        $this->assertCount(1, $prototypeView);
    }
}
