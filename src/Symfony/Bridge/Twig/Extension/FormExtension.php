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
use Symfony\Bridge\Twig\Form\TwigRendererInterface;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

/**
 * FormExtension extends Twig with form capabilities.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormExtension extends \Twig_Extension implements \Twig_Extension_InitRuntimeInterface
{
    private $renderer;

    public function __construct(TwigRendererInterface $renderer = null)
    {
        if (null !== $this->renderer) {
            @trigger_error(sprintf('Passing a Twig Form Renderer to the "%s" constructor is deprecated since version 3.2 and won\'t be possible in 4.0. Pass the Twig_Environment to the TwigRendererEngine constructor instead.', static::class), E_USER_DEPRECATED);
        }
        $this->renderer = $renderer;
    }

    /**
     * {@inheritdoc}
     *
     * To be removed in 4.0
     */
    public function initRuntime(\Twig_Environment $environment)
    {
        if (null !== $this->renderer) {
            $this->renderer->setEnvironment($environment);
        }
    }

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
            new \Twig_SimpleFunction('form_widget', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_errors', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_label', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_row', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_rest', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form', null, array('node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_start', null, array('node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_end', null, array('node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('csrf_token', array('Symfony\Bridge\Twig\Form\TwigRenderer', 'renderCsrfToken')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('humanize', array('Symfony\Bridge\Twig\Form\TwigRenderer', 'humanize')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTests()
    {
        return array(
            new \Twig_SimpleTest('selectedchoice', 'Symfony\Bridge\Twig\Extension\twig_is_selected_choice'),
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
