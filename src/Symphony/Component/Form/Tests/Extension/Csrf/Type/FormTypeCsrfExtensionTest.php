<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests\Extension\Csrf\Type;

use Symphony\Component\Form\AbstractType;
use Symphony\Component\Form\FormBuilderInterface;
use Symphony\Component\Form\FormError;
use Symphony\Component\Form\Test\TypeTestCase;
use Symphony\Component\Form\Extension\Csrf\CsrfExtension;
use Symphony\Component\Security\Csrf\CsrfToken;

class FormTypeCsrfExtensionTest_ChildType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // The form needs a child in order to trigger CSRF protection by
        // default
        $builder->add('name', 'Symphony\Component\Form\Extension\Core\Type\TextType');
    }
}

class FormTypeCsrfExtensionTest extends TypeTestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $tokenManager;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $translator;

    protected function setUp()
    {
        $this->tokenManager = $this->getMockBuilder('Symphony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock();
        $this->translator = $this->getMockBuilder('Symphony\Component\Translation\TranslatorInterface')->getMock();

        parent::setUp();
    }

    protected function tearDown()
    {
        $this->tokenManager = null;
        $this->translator = null;

        parent::tearDown();
    }

    protected function getExtensions()
    {
        return array_merge(parent::getExtensions(), array(
            new CsrfExtension($this->tokenManager, $this->translator),
        ));
    }

    public function testCsrfProtectionByDefaultIfRootAndCompound()
    {
        $view = $this->factory
            ->create('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'compound' => true,
            ))
            ->createView();

        $this->assertArrayHasKey('csrf', $view);
    }

    public function testNoCsrfProtectionByDefaultIfCompoundButNotRoot()
    {
        $view = $this->factory
            ->createNamedBuilder('root', 'Symphony\Component\Form\Extension\Core\Type\FormType')
            ->add($this->factory
                ->createNamedBuilder('form', 'Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                    'csrf_field_name' => 'csrf',
                    'compound' => true,
                ))
            )
            ->getForm()
            ->get('form')
            ->createView();

        $this->assertArrayNotHasKey('csrf', $view);
    }

    public function testNoCsrfProtectionByDefaultIfRootButNotCompound()
    {
        $view = $this->factory
            ->create('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'compound' => false,
            ))
            ->createView();

        $this->assertArrayNotHasKey('csrf', $view);
    }

    public function testCsrfProtectionCanBeDisabled()
    {
        $view = $this->factory
            ->create('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_protection' => false,
                'compound' => true,
            ))
            ->createView();

        $this->assertArrayNotHasKey('csrf', $view);
    }

    public function testGenerateCsrfToken()
    {
        $this->tokenManager->expects($this->once())
            ->method('getToken')
            ->with('TOKEN_ID')
            ->will($this->returnValue(new CsrfToken('TOKEN_ID', 'token')));

        $view = $this->factory
            ->create('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ))
            ->createView();

        $this->assertEquals('token', $view['csrf']->vars['value']);
    }

    public function testGenerateCsrfTokenUsesFormNameAsIntentionByDefault()
    {
        $this->tokenManager->expects($this->once())
            ->method('getToken')
            ->with('FORM_NAME')
            ->will($this->returnValue('token'));

        $view = $this->factory
            ->createNamed('FORM_NAME', 'Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'compound' => true,
            ))
            ->createView();

        $this->assertEquals('token', $view['csrf']->vars['value']);
    }

    public function testGenerateCsrfTokenUsesTypeClassAsIntentionIfEmptyFormName()
    {
        $this->tokenManager->expects($this->once())
            ->method('getToken')
            ->with('Symphony\Component\Form\Extension\Core\Type\FormType')
            ->will($this->returnValue('token'));

        $view = $this->factory
            ->createNamed('', 'Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
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
    public function testValidateTokenOnSubmitIfRootAndCompound($valid)
    {
        $this->tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('TOKEN_ID', 'token'))
            ->will($this->returnValue($valid));

        $form = $this->factory
            ->createBuilder('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ))
            ->add('child', 'Symphony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $form->submit(array(
            'child' => 'foobar',
            'csrf' => 'token',
        ));

        // Remove token from data
        $this->assertSame(array('child' => 'foobar'), $form->getData());

        // Validate accordingly
        $this->assertSame($valid, $form->isValid());
    }

    /**
     * @dataProvider provideBoolean
     */
    public function testValidateTokenOnSubmitIfRootAndCompoundUsesFormNameAsIntentionByDefault($valid)
    {
        $this->tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('FORM_NAME', 'token'))
            ->will($this->returnValue($valid));

        $form = $this->factory
            ->createNamedBuilder('FORM_NAME', 'Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'compound' => true,
            ))
            ->add('child', 'Symphony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $form->submit(array(
            'child' => 'foobar',
            'csrf' => 'token',
        ));

        // Remove token from data
        $this->assertSame(array('child' => 'foobar'), $form->getData());

        // Validate accordingly
        $this->assertSame($valid, $form->isValid());
    }

    /**
     * @dataProvider provideBoolean
     */
    public function testValidateTokenOnSubmitIfRootAndCompoundUsesTypeClassAsIntentionIfEmptyFormName($valid)
    {
        $this->tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('Symphony\Component\Form\Extension\Core\Type\FormType', 'token'))
            ->will($this->returnValue($valid));

        $form = $this->factory
            ->createNamedBuilder('', 'Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'compound' => true,
            ))
            ->add('child', 'Symphony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $form->submit(array(
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
        $this->tokenManager->expects($this->never())
            ->method('isTokenValid');

        $form = $this->factory
            ->createBuilder('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ))
            ->add('child', 'Symphony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $form->submit(array(
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
        $this->tokenManager->expects($this->never())
            ->method('isTokenValid');

        $form = $this->factory
            ->createNamedBuilder('root', 'Symphony\Component\Form\Extension\Core\Type\FormType')
            ->add($this->factory
                ->createNamedBuilder('form', 'Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                    'csrf_field_name' => 'csrf',
                    'csrf_token_manager' => $this->tokenManager,
                    'csrf_token_id' => 'TOKEN_ID',
                    'compound' => true,
                ))
            )
            ->getForm()
            ->get('form');

        $form->submit(array(
            'child' => 'foobar',
            'csrf' => 'token',
        ));
    }

    public function testDontValidateTokenIfRootButNotCompound()
    {
        $this->tokenManager->expects($this->never())
            ->method('isTokenValid');

        $form = $this->factory
            ->create('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => false,
            ));

        $form->submit(array(
            'csrf' => 'token',
        ));
    }

    public function testNoCsrfProtectionOnPrototype()
    {
        $prototypeView = $this->factory
            ->create('Symphony\Component\Form\Extension\Core\Type\CollectionType', null, array(
                'entry_type' => __CLASS__.'_ChildType',
                'entry_options' => array(
                    'csrf_field_name' => 'csrf',
                ),
                'prototype' => true,
                'allow_add' => true,
            ))
            ->createView()
            ->vars['prototype'];

        $this->assertArrayNotHasKey('csrf', $prototypeView);
        $this->assertCount(1, $prototypeView);
    }

    public function testsTranslateCustomErrorMessage()
    {
        $this->tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('TOKEN_ID', 'token'))
            ->will($this->returnValue(false));

        $this->translator->expects($this->once())
             ->method('trans')
             ->with('Foobar')
             ->will($this->returnValue('[trans]Foobar[/trans]'));

        $form = $this->factory
            ->createBuilder('Symphony\Component\Form\Extension\Core\Type\FormType', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_message' => 'Foobar',
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ))
            ->getForm();

        $form->submit(array(
            'csrf' => 'token',
        ));

        $errors = $form->getErrors();
        $expected = new FormError('[trans]Foobar[/trans]');
        $expected->setOrigin($form);

        $this->assertGreaterThan(0, count($errors));
        $this->assertEquals($expected, $errors[0]);
    }
}
