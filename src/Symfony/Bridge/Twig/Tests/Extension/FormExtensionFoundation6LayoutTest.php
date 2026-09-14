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
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Tests\Extension\Fixtures\StubTranslator;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\PercentType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

/**
 * Foundation 6 extends the div layout, so it only overrides the blocks that render
 * differently.
 */
class FormExtensionFoundation6LayoutTest extends AbstractDivLayoutTestCase
{
    public function testMoney()
    {
        $form = $this->factory->createNamed('name', MoneyType::class, 1234.56, [
            'currency' => 'EUR',
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
            '/div
    [@class="input-group"]
    [
        ./span
            [@class="input-group-label"]
            [contains(.., "€")]
        /following-sibling::input
            [@id="my&id"]
            [@type="text"]
            [@name="name"]
            [@class="my&class input-group-field"]
            [@value="1234.56"]
    ]
'
        );
    }

    public function testMoneyWithoutPattern()
    {
        $form = $this->factory->createNamed('name', MoneyType::class, 1234.56, [
            'currency' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), [],
            '/input
    [@type="text"]
    [@name="name"]
    [@value="1234.56"]
'
        );
    }

    public function testPercent()
    {
        $form = $this->factory->createNamed('name', PercentType::class, 0.1, ['rounding_mode' => \NumberFormatter::ROUND_CEILING]);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
            '/div
    [@class="input-group"]
    [
        ./input
            [@id="my&id"]
            [@type="text"]
            [@name="name"]
            [@class="my&class input-group-field"]
            [@value="10"]
        /following-sibling::span
            [@class="input-group-label"]
            [contains(.., "%")]
    ]
'
        );
    }

    public function testPercentCustomSymbol()
    {
        $form = $this->factory->createNamed('name', PercentType::class, 0.1, ['symbol' => '‱', 'rounding_mode' => \NumberFormatter::ROUND_CEILING]);

        $this->assertWidgetMatchesXpath($form->createView(), ['id' => 'my&id', 'attr' => ['class' => 'my&class']],
            '/div
    [@class="input-group"]
    [
        ./input
            [@id="my&id"]
            [@type="text"]
            [@name="name"]
            [@class="my&class input-group-field"]
            [@value="10"]
        /following-sibling::span
            [@class="input-group-label"]
            [contains(.., "‱")]
    ]
'
        );
    }

    public function testButton()
    {
        $form = $this->factory->createNamed('name', ButtonType::class);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/button[@type="button"][@name="name"][.="[trans]Name[/trans]"][@class="my&class button"]'
        );
    }

    public function testButtonlabelWithoutTranslation()
    {
        $form = $this->factory->createNamed('name', ButtonType::class, null, [
            'translation_domain' => false,
        ]);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/button[@type="button"][@name="name"][.="Name"][@class="my&class button"]'
        );
    }

    public function testSubmit()
    {
        $form = $this->factory->createNamed('name', SubmitType::class);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/button[@type="submit"][@name="name"][@class="my&class button"]'
        );
    }

    public function testReset()
    {
        $form = $this->factory->createNamed('name', ResetType::class);

        $this->assertWidgetMatchesXpath($form->createView(), ['attr' => ['class' => 'my&class']],
            '/button[@type="reset"][@name="name"][@class="my&class button"]'
        );
    }

    public function testButtonAttributes()
    {
        $form = $this->factory->createNamed('button', ButtonType::class, null, [
            'disabled' => true,
            'attr' => ['class' => 'foobar', 'data-foo' => 'bar'],
        ]);

        $html = $this->renderWidget($form->createView());

        // compare plain HTML to check the whitespace
        $this->assertSame('<button type="button" id="button" name="button" disabled="disabled" class="foobar button" data-foo="bar">[trans]Button[/trans]</button>', $html);
    }

    public function testButtonAttributeNameRepeatedIfTrue()
    {
        $form = $this->factory->createNamed('button', ButtonType::class, null, [
            'attr' => ['foo' => true],
        ]);

        $html = $this->renderWidget($form->createView());

        // foo="foo"
        $this->assertSame('<button type="button" id="button" name="button" foo="foo" class="button">[trans]Button[/trans]</button>', $html);
    }

    public function testCheckboxRowWithSwitch()
    {
        $form = $this->factory->createNamed('name', CheckboxType::class);

        $html = $this->renderRow($form->createView(), ['attr' => ['class' => 'switch-input']]);

        $this->assertMatchesXpath($html,
            '/label
    [@for="name"]
    [.="[trans]Name[/trans]"]
/following-sibling::input
    [@type="checkbox"]
    [@name="name"]
    [@class="switch-input"]
/following-sibling::label
    [@class="switch-paddle"]
    [@for="name"]
'
        );
    }

    public static function themeBlockInheritanceProvider(): array
    {
        return [
            [['theme.html.twig']],
        ];
    }

    public static function themeInheritanceProvider(): array
    {
        return [
            [['parent_label.html.twig'], ['child_label.html.twig']],
        ];
    }

    protected function getTemplatePaths(): array
    {
        return [
            __DIR__.'/../../Resources/views/Form',
            __DIR__.'/Fixtures/templates/form',
        ];
    }

    protected function getTwigExtensions(): array
    {
        return [
            new TranslationExtension(new StubTranslator()),
            new FormExtension(),
        ];
    }

    protected function getTwigGlobals(): array
    {
        return [
            'global' => '',
            // the value can be any template that exists
            'dynamic_template_name' => 'child_label',
        ];
    }

    protected function getThemes(): array
    {
        return [
            'foundation_6_layout.html.twig',
            'custom_widgets.html.twig',
        ];
    }
}
