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

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Security\Csrf\CsrfToken;

class FormTypeCsrfExtensionTest_ChildType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // The form needs a child in order to trigger CSRF protection by
        // default
        $builder->add('name', 'Symfony\Component\Form\Extension\Core\Type\TextType');
    }
}

class FormTypeCsrfExtensionTest extends TypeTestCase
{
    /**
     * @var MockObject
     */
    protected $tokenManager;

    /**
     * @var MockObject
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
        return array_merge(parent::getExtensions(), [
            new CsrfExtension($this->tokenManager, $this->translator),
        ]);
    }

    public function testCsrfProtectionByDefaultIfRootAndCompound()
    {
        $view = $this->factory
            ->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'compound' => true,
            ])
            ->createView();

        $this->assertArrayHasKey('csrf', $view);
    }

    public function testNoCsrfProtectionByDefaultIfCompoundButNotRoot()
    {
        $view = $this->factory
            ->createNamedBuilder('root', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add($this->factory
                ->createNamedBuilder('form', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                    'csrf_field_name' => 'csrf',
                    'compound' => true,
                ])
            )
            ->getForm()
            ->get('form')
            ->createView();

        $this->assertArrayNotHasKey('csrf', $view);
    }

    public function testNoCsrfProtectionByDefaultIfRootButNotCompound()
    {
        $view = $this->factory
            ->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'compound' => false,
            ])
            ->createView();

        $this->assertArrayNotHasKey('csrf', $view);
    }

    public function testCsrfProtectionCanBeDisabled()
    {
        $view = $this->factory
            ->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_protection' => false,
                'compound' => true,
            ])
            ->createView();

        $this->assertArrayNotHasKey('csrf', $view);
    }

    public function testGenerateCsrfToken()
    {
        $this->tokenManager->expects($this->once())
            ->method('getToken')
            ->with('TOKEN_ID')
            ->willReturn(new CsrfToken('TOKEN_ID', 'token'));

        $view = $this->factory
            ->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ])
            ->createView();

        $this->assertEquals('token', $view['csrf']->vars['value']);
    }

    public function testGenerateCsrfTokenUsesFormNameAsIntentionByDefault()
    {
        $this->tokenManager->expects($this->once())
            ->method('getToken')
            ->with('FORM_NAME')
            ->willReturn(new CsrfToken('TOKEN_ID', 'token'));

        $view = $this->factory
            ->createNamed('FORM_NAME', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'compound' => true,
            ])
            ->createView();

        $this->assertEquals('token', $view['csrf']->vars['value']);
    }

    public function testGenerateCsrfTokenUsesTypeClassAsIntentionIfEmptyFormName()
    {
        $this->tokenManager->expects($this->once())
            ->method('getToken')
            ->with('Symfony\Component\Form\Extension\Core\Type\FormType')
            ->willReturn(new CsrfToken('TOKEN_ID', 'token'));

        $view = $this->factory
            ->createNamed('', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'compound' => true,
            ])
            ->createView();

        $this->assertEquals('token', $view['csrf']->vars['value']);
    }

    public function provideBoolean()
    {
        return [
            [true],
            [false],
        ];
    }

    /**
     * @dataProvider provideBoolean
     */
    public function testValidateTokenOnSubmitIfRootAndCompound($valid)
    {
        $this->tokenManager->expects($this->once())
            ->method('isTokenValid')
            ->with(new CsrfToken('TOKEN_ID', 'token'))
            ->willReturn($valid);

        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ])
            ->add('child', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $form->submit([
            'child' => 'foobar',
            'csrf' => 'token',
        ]);

        // Remove token from data
        $this->assertSame(['child' => 'foobar'], $form->getData());

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
            ->willReturn($valid);

        $form = $this->factory
            ->createNamedBuilder('FORM_NAME', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'compound' => true,
            ])
            ->add('child', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $form->submit([
            'child' => 'foobar',
            'csrf' => 'token',
        ]);

        // Remove token from data
        $this->assertSame(['child' => 'foobar'], $form->getData());

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
            ->willReturn($valid);

        $form = $this->factory
            ->createNamedBuilder('', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'compound' => true,
            ])
            ->add('child', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $form->submit([
            'child' => 'foobar',
            'csrf' => 'token',
        ]);

        // Remove token from data
        $this->assertSame(['child' => 'foobar'], $form->getData());

        // Validate accordingly
        $this->assertSame($valid, $form->isValid());
    }

    public function testFailIfRootAndCompoundAndTokenMissing()
    {
        $this->tokenManager->expects($this->never())
            ->method('isTokenValid');

        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ])
            ->add('child', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->getForm();

        $form->submit([
            'child' => 'foobar',
            // token is missing
        ]);

        // Remove token from data
        $this->assertSame(['child' => 'foobar'], $form->getData());

        // Validate accordingly
        $this->assertFalse($form->isValid());
    }

    public function testDontValidateTokenIfCompoundButNoRoot()
    {
        $this->tokenManager->expects($this->never())
            ->method('isTokenValid');

        $form = $this->factory
            ->createNamedBuilder('root', 'Symfony\Component\Form\Extension\Core\Type\FormType')
            ->add($this->factory
                ->createNamedBuilder('form', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                    'csrf_field_name' => 'csrf',
                    'csrf_token_manager' => $this->tokenManager,
                    'csrf_token_id' => 'TOKEN_ID',
                    'compound' => true,
                ])
            )
            ->getForm()
            ->get('form');

        $form->submit([
            'child' => 'foobar',
            'csrf' => 'token',
        ]);
    }

    public function testDontValidateTokenIfRootButNotCompound()
    {
        $this->tokenManager->expects($this->never())
            ->method('isTokenValid');

        $form = $this->factory
            ->create('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => false,
            ]);

        $form->submit([
            'csrf' => 'token',
        ]);
    }

    public function testNoCsrfProtectionOnPrototype()
    {
        $prototypeView = $this->factory
            ->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, [
                'entry_type' => __CLASS__.'_ChildType',
                'entry_options' => [
                    'csrf_field_name' => 'csrf',
                ],
                'prototype' => true,
                'allow_add' => true,
            ])
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
            ->willReturn(false);

        $this->translator->expects($this->once())
             ->method('trans')
             ->with('Foobar')
             ->willReturn('[trans]Foobar[/trans]');

        $form = $this->factory
            ->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', null, [
                'csrf_field_name' => 'csrf',
                'csrf_token_manager' => $this->tokenManager,
                'csrf_message' => 'Foobar',
                'csrf_token_id' => 'TOKEN_ID',
                'compound' => true,
            ])
            ->getForm();

        $form->submit([
            'csrf' => 'token',
        ]);

        $errors = $form->getErrors();
        $expected = new FormError('[trans]Foobar[/trans]');
        $expected->setOrigin($form);

        $this->assertGreaterThan(0, \count($errors));
        $this->assertEquals($expected, $errors[0]);
    }
}
