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
use Symfony\Bridge\Twig\Test\FormLayoutTestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\RadioType;

class FormExtensionChoiceLabelTest extends FormLayoutTestCase
{
    private const THEMES = [
        'form_div_layout.html.twig',
        'form_table_layout.html.twig',
        'bootstrap_3_layout.html.twig',
        'bootstrap_3_horizontal_layout.html.twig',
        'bootstrap_4_layout.html.twig',
        'bootstrap_4_horizontal_layout.html.twig',
        'bootstrap_5_layout.html.twig',
        'bootstrap_5_horizontal_layout.html.twig',
        'foundation_5_layout.html.twig',
        'foundation_6_layout.html.twig',
        'tailwind_2_layout.html.twig',
    ];

    public function testExpandedChoiceLabels()
    {
        foreach (self::THEMES as $theme) {
            $this->assertExpandedChoiceLabels($theme, false, true, '');
            $this->assertExpandedChoiceLabels($theme, false, true, 'radio-inline');
            $this->assertExpandedChoiceLabels($theme, false, false, '');
            $this->assertExpandedChoiceLabels($theme, true, true, '');
            $this->assertExpandedChoiceLabels($theme, true, true, 'checkbox-inline');

            if (str_starts_with($theme, 'bootstrap_4_')) {
                $this->assertExpandedChoiceLabels($theme, false, true, 'radio-custom');
                $this->assertExpandedChoiceLabels($theme, false, true, 'radio-custom radio-inline');
            }
        }
    }

    public function testRequiredRadioAndCheckboxLabels()
    {
        foreach (self::THEMES as $theme) {
            foreach ([RadioType::class, CheckboxType::class] as $type) {
                $view = $this->factory->createNamed('name', $type)->createView();
                $this->setTheme($view, [$theme], false);
                $html = $this->renderRow($view);

                $this->assertMatchesXpath($html, '//input[@required]');
                $this->assertMatchesXpath($html, '//label[contains(concat(" ", normalize-space(@class), " "), " required ")]');
            }
        }
    }

    protected function getTemplatePaths(): array
    {
        return [__DIR__.'/../../Resources/views/Form'];
    }

    protected function getTwigExtensions(): array
    {
        return [new FormExtension(), new TranslationExtension()];
    }

    protected function getThemes(): array
    {
        return ['form_div_layout.html.twig'];
    }

    private function assertExpandedChoiceLabels(string $theme, bool $multiple, bool $required, string $labelClass): void
    {
        // The renderer caches the blocks of child views by name, so each theme renders a form with a different name
        $view = $this->factory->createNamed(strstr($theme, '.', true), ChoiceType::class, null, [
            'choices' => ['Yes' => true, 'No' => false],
            'expanded' => true,
            'multiple' => $multiple,
            'required' => $required,
            'placeholder' => false,
            'label' => 'Visibility',
            'label_attr' => ['class' => $labelClass],
        ])->createView();
        $this->setTheme($view, [$theme], false);
        $html = $this->renderRow($view);

        $this->assertMatchesXpath($html, '//input[@type="'.($multiple ? 'checkbox' : 'radio').'"]', 2);
        $this->assertMatchesXpath($html, '//input[@required]', $required && !$multiple ? 2 : 0);
        $this->assertMatchesXpath($html, '//label[normalize-space(.)="Yes" or normalize-space(.)="No"]', 2);
        $this->assertMatchesXpath($html, '//label[normalize-space(.)="Yes" or normalize-space(.)="No"][contains(concat(" ", normalize-space(@class), " "), " required ")]', 0);
        $this->assertMatchesXpath($html, '//*[self::label or self::legend][normalize-space(.)="Visibility"]');
        $this->assertMatchesXpath($html, '//*[self::label or self::legend][normalize-space(.)="Visibility"][contains(concat(" ", normalize-space(@class), " "), " required ")]', $required ? 1 : 0);

        if (str_contains($labelClass, 'radio-custom')) {
            $this->assertMatchesXpath($html, '//div[contains(concat(" ", normalize-space(@class), " "), " custom-radio ")]/label[contains(concat(" ", normalize-space(@class), " "), " custom-control-label ")]', 2);
        }
    }
}
