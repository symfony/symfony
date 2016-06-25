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
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

/**
 * FormExtension extends Twig with form capabilities.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormExtension extends \Twig_Extension implements \Twig_Extension_InitRuntimeInterface
{
    /**
     * This property is public so that it can be accessed directly from compiled
     * templates without having to call a getter, which slightly decreases performance.
     *
     * @var TwigRendererInterface
     */
    public $renderer;

    public function __construct(TwigRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * {@inheritdoc}
     */
    public function initRuntime(\Twig_Environment $environment)
    {
        $this->renderer->setEnvironment($environment);
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
            new \Twig_SimpleFunction('form_enctype', null, array('node_class' => 'Symfony\Bridge\Twig\Node\FormEnctypeNode', 'is_safe' => array('html'), 'deprecated' => true, 'alternative' => 'form_start')),
            new \Twig_SimpleFunction('form_widget', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_errors', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_label', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_row', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_rest', null, array('node_class' => 'Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form', null, array('node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_start', null, array('node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('form_end', null, array('node_class' => 'Symfony\Bridge\Twig\Node\RenderBlockNode', 'is_safe' => array('html'))),
            new \Twig_SimpleFunction('csrf_token', array($this, 'renderCsrfToken')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('humanize', array($this, 'humanize')),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getTests()
    {
        return array(
            new \Twig_SimpleTest('selectedchoice', array($this, 'isSelectedChoice')),
        );
    }

    /**
     * Renders a CSRF token.
     *
     * @param string $intention The intention of the protected action.
     *
     * @return string A CSRF token.
     */
    public function renderCsrfToken($intention)
    {
        return $this->renderer->renderCsrfToken($intention);
    }

    /**
     * Makes a technical name human readable.
     *
     * @param string $text The text to humanize.
     *
     * @return string The humanized text.
     */
    public function humanize($text)
    {
        return $this->renderer->humanize($text);
    }

    /**
     * Returns whether a choice is selected for a given form value.
     *
     * Unfortunately Twig does not support an efficient way to execute the
     * "is_selected" closure passed to the template by ChoiceType. It is faster
     * to implement the logic here (around 65ms for a specific form).
     *
     * Directly implementing the logic here is also faster than doing so in
     * ChoiceView (around 30ms).
     *
     * The worst option tested so far is to implement the logic in ChoiceView
     * and access the ChoiceView method directly in the template. Doing so is
     * around 220ms slower than doing the method call here in the filter. Twig
     * seems to be much more efficient at executing filters than at executing
     * methods of an object.
     *
     * @param ChoiceView   $choice        The choice to check.
     * @param string|array $selectedValue The selected value to compare.
     *
     * @return bool Whether the choice is selected.
     *
     * @see ChoiceView::isSelected()
     */
    public function isSelectedChoice(ChoiceView $choice, $selectedValue)
    {
        if (is_array($selectedValue)) {
            return in_array($choice->value, $selectedValue, true);
        }

        return $choice->value === $selectedValue;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form';
    }
}
