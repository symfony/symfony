<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Extension;

use Symphony\Bridge\Twig\TokenParser\FormThemeTokenParser;
use Symphony\Component\Form\ChoiceList\View\ChoiceView;
use Symphony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * FormExtension extends Twig with form capabilities.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return array(
            // {% form_theme form "SomeBundle::widgets.twig" %}
            new FormThemeTokenParser(),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new TwigFunction('form_widget', null, array('node_class' => 'Symphony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new TwigFunction('form_errors', null, array('node_class' => 'Symphony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new TwigFunction('form_label', null, array('node_class' => 'Symphony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new TwigFunction('form_help', null, array('node_class' => 'Symphony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new TwigFunction('form_row', null, array('node_class' => 'Symphony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new TwigFunction('form_rest', null, array('node_class' => 'Symphony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new TwigFunction('form', null, array('node_class' => 'Symphony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new TwigFunction('form_start', null, array('node_class' => 'Symphony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new TwigFunction('form_end', null, array('node_class' => 'Symphony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new TwigFunction('csrf_token', array('Symphony\Component\Form\FormRenderer', 'renderCsrfToken')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new TwigFilter('humanize', array('Symphony\Component\Form\FormRenderer', 'humanize')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTests()
    {
        return array(
            new TwigTest('selectedchoice', 'Symphony\Bridge\Twig\Extension\twig_is_selected_choice'),
            new TwigTest('rootform', 'Symphony\Bridge\Twig\Extension\twig_is_root_form'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form';
    }
}

/**
 * Returns whether a choice is selected for a given form value.
 *
 * This is a function and not callable due to performance reasons.
 *
 * @param string|array $selectedValue The selected value to compare
 *
 * @return bool Whether the choice is selected
 *
 * @see ChoiceView::isSelected()
 */
function twig_is_selected_choice(ChoiceView $choice, $selectedValue)
{
    if (is_array($selectedValue)) {
        return in_array($choice->value, $selectedValue, true);
    }

    return $choice->value === $selectedValue;
}

/**
 * @internal
 */
function twig_is_root_form(FormView $formView)
{
    return null === $formView->parent;
}
