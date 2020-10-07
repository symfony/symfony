<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\TokenParser\FormThemeTokenParser;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * FormExtension extends Twig with form capabilities.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class FormExtension extends AbstractExtension
{
    private $translator;

    public function __construct(TranslatorInterface $translator = null)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers(): array
    {
        return [
            // {% form_theme form "SomeBundle::widgets.twig" %}
            new FormThemeTokenParser(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('form_widget', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_errors', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_label', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_help', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_row', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_rest', null, ['node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form', null, ['node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_start', null, ['node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('form_end', null, ['node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => ['html']]),
            new TwigFunction('csrf_token', ['Symfony\Component\Form\FormRenderer', 'renderCsrfToken']),
            new TwigFunction('form_parent', 'Symfony\Bridge\Twig\Extension\twig_get_form_parent'),
            new TwigFunction('field_name', [$this, 'getFieldName']),
            new TwigFunction('field_value', [$this, 'getFieldValue']),
            new TwigFunction('field_label', [$this, 'getFieldLabel']),
            new TwigFunction('field_help', [$this, 'getFieldHelp']),
            new TwigFunction('field_errors', [$this, 'getFieldErrors']),
            new TwigFunction('field_choices', [$this, 'getFieldChoices']),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('humanize', ['Symfony\Component\Form\FormRenderer', 'humanize']),
            new TwigFilter('form_encode_currency', ['Symfony\Component\Form\FormRenderer', 'encodeCurrency'], ['is_safe' => ['html'], 'needs_environment' => true]),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getTests(): array
    {
        return [
            new TwigTest('selectedchoice', 'Symfony\Bridge\Twig\Extension\twig_is_selected_choice'),
            new TwigTest('rootform', 'Symfony\Bridge\Twig\Extension\twig_is_root_form'),
        ];
    }

    public function getFieldName(FormView $view): string
    {
        $view->setRendered();

        return $view->vars['full_name'];
    }

    /**
     * @return string|array
     */
    public function getFieldValue(FormView $view)
    {
        return $view->vars['value'];
    }

    public function getFieldLabel(FormView $view): string
    {
        return $this->createFieldTranslation(
            $view->vars['label'],
            $view->vars['label_translation_parameters'] ?: [],
            $view->vars['translation_domain']
        );
    }

    public function getFieldHelp(FormView $view): string
    {
        return $this->createFieldTranslation(
            $view->vars['help'],
            $view->vars['help_translation_parameters'] ?: [],
            $view->vars['translation_domain']
        );
    }

    /**
     * @return string[]
     */
    public function getFieldErrors(FormView $view): iterable
    {
        /** @var FormError $error */
        foreach ($view->vars['errors'] as $error) {
            yield $error->getMessage();
        }
    }

    /**
     * @return string[]|string[][]
     */
    public function getFieldChoices(FormView $view): iterable
    {
        yield from $this->createFieldChoicesList($view->vars['choices'], $view->vars['choice_translation_domain']);
    }

    private function createFieldChoicesList(iterable $choices, $translationDomain): iterable
    {
        foreach ($choices as $choice) {
            $translatableLabel = $this->createFieldTranslation($choice->label, [], $translationDomain);

            if ($choice instanceof ChoiceGroupView) {
                yield $translatableLabel => $this->createFieldChoicesList($choice, $translationDomain);

                continue;
            }

            /* @var ChoiceView $choice */
            yield $translatableLabel => $choice->value;
        }
    }

    private function createFieldTranslation(?string $value, array $parameters, $domain): string
    {
        if (!$this->translator || !$value || false === $domain) {
            return $value;
        }

        return $this->translator->trans($value, $parameters, $domain);
    }
}

/**
 * Returns whether a choice is selected for a given form value.
 *
 * This is a function and not callable due to performance reasons.
 *
 * @param string|array $selectedValue The selected value to compare
 *
 * @see ChoiceView::isSelected()
 */
function twig_is_selected_choice(ChoiceView $choice, $selectedValue): bool
{
    if (\is_array($selectedValue)) {
        return \in_array($choice->value, $selectedValue, true);
    }

    return $choice->value === $selectedValue;
}

/**
 * @internal
 */
function twig_is_root_form(FormView $formView): bool
{
    return null === $formView->parent;
}

/**
 * @internal
 */
function twig_get_form_parent(FormView $formView): ?FormView
{
    return $formView->parent;
}
