<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\Extension;

use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Test\FormIntegrationTestCase;

class FormExtensionFieldHelpersTest extends FormIntegrationTestCase
{
    /**
     * @var FormExtension
     */
    private $rawExtension;

    /**
     * @var FormExtension
     */
    private $translatorExtension;

    /**
     * @var FormView
     */
    private $view;

    protected function getTypes()
    {
        return [new TextType(), new ChoiceType()];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rawExtension = new FormExtension();
        $this->translatorExtension = new FormExtension(new StubTranslator());

        $data = [
            'username' => 'tgalopin',
            'choice_multiple' => ['sugar', 'salt'],
        ];

        $form = $this->factory->createNamedBuilder('register', FormType::class, $data)
            ->add('username', TextType::class, [
                'label' => 'base.username',
                'label_translation_parameters' => ['%label_brand%' => 'Symfony'],
                'help' => 'base.username_help',
                'help_translation_parameters' => ['%help_brand%' => 'Symfony'],
                'translation_domain' => 'forms',
            ])
            ->add('choice_flat', ChoiceType::class, [
                'choices' => [
                    'base.yes' => 'yes',
                    'base.no' => 'no',
                ],
                'choice_translation_domain' => 'forms',
            ])
            ->add('choice_grouped', ChoiceType::class, [
                'choices' => [
                    'base.europe' => [
                        'base.fr' => 'fr',
                        'base.de' => 'de',
                    ],
                    'base.asia' => [
                        'base.cn' => 'cn',
                        'base.jp' => 'jp',
                    ],
                ],
                'choice_translation_domain' => 'forms',
                'label_format' => 'label format for field "%name%" with id "%id%"',
            ])
            ->add('choice_multiple', ChoiceType::class, [
                'choices' => [
                    'base.sugar' => 'sugar',
                    'base.salt' => 'salt',
                ],
                'multiple' => true,
                'expanded' => true,
                'label' => false,
            ])
            ->getForm()
        ;

        $form->get('username')->addError(new FormError('username.max_length'));

        $this->view = $form->createView();
    }

    public function testFieldName()
    {
        self::assertFalse($this->view->children['username']->isRendered());
        self::assertSame('register[username]', $this->rawExtension->getFieldName($this->view->children['username']));
        self::assertTrue($this->view->children['username']->isRendered());
    }

    public function testFieldValue()
    {
        self::assertSame('tgalopin', $this->rawExtension->getFieldValue($this->view->children['username']));
        self::assertSame(['sugar', 'salt'], $this->rawExtension->getFieldValue($this->view->children['choice_multiple']));
    }

    public function testFieldLabel()
    {
        self::assertSame('base.username', $this->rawExtension->getFieldLabel($this->view->children['username']));
    }

    public function testFieldTranslatedLabel()
    {
        self::assertSame('[trans]base.username[/trans]', $this->translatorExtension->getFieldLabel($this->view->children['username']));
    }

    public function testFieldLabelFromFormat()
    {
        self::assertSame('label format for field "choice_grouped" with id "register_choice_grouped"', $this->rawExtension->getFieldLabel($this->view->children['choice_grouped']));
    }

    public function testFieldLabelFallsBackToName()
    {
        self::assertSame('Choice flat', $this->rawExtension->getFieldLabel($this->view->children['choice_flat']));
    }

    public function testFieldLabelReturnsNullWhenLabelIsDisabled()
    {
        self::assertNull($this->rawExtension->getFieldLabel($this->view->children['choice_multiple']));
    }

    public function testFieldHelp()
    {
        self::assertSame('base.username_help', $this->rawExtension->getFieldHelp($this->view->children['username']));
    }

    public function testFieldTranslatedHelp()
    {
        self::assertSame('[trans]base.username_help[/trans]', $this->translatorExtension->getFieldHelp($this->view->children['username']));
    }

    public function testFieldErrors()
    {
        $errors = $this->rawExtension->getFieldErrors($this->view->children['username']);
        self::assertSame(['username.max_length'], iterator_to_array($errors));
    }

    public function testFieldTranslatedErrors()
    {
        $errors = $this->translatorExtension->getFieldErrors($this->view->children['username']);
        self::assertSame(['username.max_length'], iterator_to_array($errors));
    }

    public function testFieldChoicesFlat()
    {
        $choices = $this->rawExtension->getFieldChoices($this->view->children['choice_flat']);

        $choicesArray = [];
        foreach ($choices as $label => $value) {
            $choicesArray[] = ['label' => $label, 'value' => $value];
        }

        self::assertCount(2, $choicesArray);

        self::assertSame('yes', $choicesArray[0]['value']);
        self::assertSame('base.yes', $choicesArray[0]['label']);

        self::assertSame('no', $choicesArray[1]['value']);
        self::assertSame('base.no', $choicesArray[1]['label']);
    }

    public function testFieldTranslatedChoicesFlat()
    {
        $choices = $this->translatorExtension->getFieldChoices($this->view->children['choice_flat']);

        $choicesArray = [];
        foreach ($choices as $label => $value) {
            $choicesArray[] = ['label' => $label, 'value' => $value];
        }

        self::assertCount(2, $choicesArray);

        self::assertSame('yes', $choicesArray[0]['value']);
        self::assertSame('[trans]base.yes[/trans]', $choicesArray[0]['label']);

        self::assertSame('no', $choicesArray[1]['value']);
        self::assertSame('[trans]base.no[/trans]', $choicesArray[1]['label']);
    }

    public function testFieldChoicesGrouped()
    {
        $choices = $this->rawExtension->getFieldChoices($this->view->children['choice_grouped']);

        $choicesArray = [];
        foreach ($choices as $groupLabel => $groupChoices) {
            $groupChoicesArray = [];
            foreach ($groupChoices as $label => $value) {
                $groupChoicesArray[] = ['label' => $label, 'value' => $value];
            }

            $choicesArray[] = ['label' => $groupLabel, 'choices' => $groupChoicesArray];
        }

        self::assertCount(2, $choicesArray);

        self::assertCount(2, $choicesArray[0]['choices']);
        self::assertSame('base.europe', $choicesArray[0]['label']);

        self::assertSame('fr', $choicesArray[0]['choices'][0]['value']);
        self::assertSame('base.fr', $choicesArray[0]['choices'][0]['label']);

        self::assertSame('de', $choicesArray[0]['choices'][1]['value']);
        self::assertSame('base.de', $choicesArray[0]['choices'][1]['label']);

        self::assertCount(2, $choicesArray[1]['choices']);
        self::assertSame('base.asia', $choicesArray[1]['label']);

        self::assertSame('cn', $choicesArray[1]['choices'][0]['value']);
        self::assertSame('base.cn', $choicesArray[1]['choices'][0]['label']);

        self::assertSame('jp', $choicesArray[1]['choices'][1]['value']);
        self::assertSame('base.jp', $choicesArray[1]['choices'][1]['label']);
    }

    public function testFieldTranslatedChoicesGrouped()
    {
        $choices = $this->translatorExtension->getFieldChoices($this->view->children['choice_grouped']);

        $choicesArray = [];
        foreach ($choices as $groupLabel => $groupChoices) {
            $groupChoicesArray = [];
            foreach ($groupChoices as $label => $value) {
                $groupChoicesArray[] = ['label' => $label, 'value' => $value];
            }

            $choicesArray[] = ['label' => $groupLabel, 'choices' => $groupChoicesArray];
        }

        self::assertCount(2, $choicesArray);

        self::assertCount(2, $choicesArray[0]['choices']);
        self::assertSame('[trans]base.europe[/trans]', $choicesArray[0]['label']);

        self::assertSame('fr', $choicesArray[0]['choices'][0]['value']);
        self::assertSame('[trans]base.fr[/trans]', $choicesArray[0]['choices'][0]['label']);

        self::assertSame('de', $choicesArray[0]['choices'][1]['value']);
        self::assertSame('[trans]base.de[/trans]', $choicesArray[0]['choices'][1]['label']);

        self::assertCount(2, $choicesArray[1]['choices']);
        self::assertSame('[trans]base.asia[/trans]', $choicesArray[1]['label']);

        self::assertSame('cn', $choicesArray[1]['choices'][0]['value']);
        self::assertSame('[trans]base.cn[/trans]', $choicesArray[1]['choices'][0]['label']);

        self::assertSame('jp', $choicesArray[1]['choices'][1]['value']);
        self::assertSame('[trans]base.jp[/trans]', $choicesArray[1]['choices'][1]['label']);
    }

    public function testFieldChoicesMultiple()
    {
        $choices = $this->rawExtension->getFieldChoices($this->view->children['choice_multiple']);

        $choicesArray = [];
        foreach ($choices as $label => $value) {
            $choicesArray[] = ['label' => $label, 'value' => $value];
        }

        self::assertCount(2, $choicesArray);

        self::assertSame('sugar', $choicesArray[0]['value']);
        self::assertSame('base.sugar', $choicesArray[0]['label']);

        self::assertSame('salt', $choicesArray[1]['value']);
        self::assertSame('base.salt', $choicesArray[1]['label']);
    }

    public function testFieldTranslatedChoicesMultiple()
    {
        $choices = $this->translatorExtension->getFieldChoices($this->view->children['choice_multiple']);

        $choicesArray = [];
        foreach ($choices as $label => $value) {
            $choicesArray[] = ['label' => $label, 'value' => $value];
        }

        self::assertCount(2, $choicesArray);

        self::assertSame('sugar', $choicesArray[0]['value']);
        self::assertSame('[trans]base.sugar[/trans]', $choicesArray[0]['label']);

        self::assertSame('salt', $choicesArray[1]['value']);
        self::assertSame('[trans]base.salt[/trans]', $choicesArray[1]['label']);
    }
}
