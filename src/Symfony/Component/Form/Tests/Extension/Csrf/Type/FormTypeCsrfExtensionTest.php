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
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Security\Csrf\CsrfToken;

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
        $this->tokenManager = $this->getMockBuilder('Symfony\Component\Security\Csrf\CsrfTokenManagerInterface')->getMock();
        $this->translator = $this->getMockBuilder('Symfony\Component\Translation\TranslatorInterface')->getMock();

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
        $this->tokenManager->expects($this->once())
            ->method('getToken')
            ->with('TOKEN_ID')
            ->will($this->returnValue(new CsrfToken('TOKEN_ID', 'token')));

        $view = $this->factory
            ->create('form', null, array(
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
            ->createNamed('FORM_NAME', 'form', null, array(
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
            ->with('Symfony\Component\Form\Extension\Core\Type\FormType')
            ->will($this->returnValue('token'));

        $view = $this->factory
            ->createNamed('', 'form', null, array(
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
            ->createBuilder('form', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ))
            ->add('child', 'text')
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
            ->createNamedBuilder('FORM_NAME', 'form', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'compound' => true,
            ))
            ->add('child', 'text')
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
            ->with(new CsrfToken('Symfony\Component\Form\Extension\Core\Type\FormType', 'token'))
            ->will($this->returnValue($valid));

        $form = $this->factory
            ->createNamedBuilder('', 'form', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'compound' => true,
            ))
            ->add('child', 'text')
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
            ->createBuilder('form', null, array(
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ))
            ->add('child', 'text')
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
            ->createNamedBuilder('root', 'form')
            ->add($this->factory
                ->createNamedBuilder('form', 'form', null, array(
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
            ->create('form', null, array(
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
            ->createBuilder('form', null, array(
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
