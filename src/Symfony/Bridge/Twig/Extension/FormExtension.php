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
class FormExtension extends \Twig_Extension
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
            'form_enctype' => new \Twig_Function_Node('Symfony\Bridge\Twig\Node\FormEnctypeNode', array('is_safe' => array('html'))),
            'form_widget'  => new \Twig_Function_Node('Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', array('is_safe' => array('html'))),
            'form_errors'  => new \Twig_Function_Node('Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', array('is_safe' => array('html'))),
            'form_label'   => new \Twig_Function_Node('Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', array('is_safe' => array('html'))),
            'form_row'     => new \Twig_Function_Node('Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', array('is_safe' => array('html'))),
            'form_rest'    => new \Twig_Function_Node('Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode', array('is_safe' => array('html'))),
            'form'         => new \Twig_Function_Node('Symfony\Bridge\Twig\Node\RenderBlockNode', array('is_safe' => array('html'))),
            'form_start'   => new \Twig_Function_Node('Symfony\Bridge\Twig\Node\RenderBlockNode', array('is_safe' => array('html'))),
            'form_end'     => new \Twig_Function_Node('Symfony\Bridge\Twig\Node\RenderBlockNode', array('is_safe' => array('html'))),
            'csrf_token'   => new \Twig_Function_Method($this, 'renderer->renderCsrfToken'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('humanize', array($this->renderer, 'humanize')),
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
     * @return bool    Whether the choice is selected.
     *
     * @see ChoiceView::isSelected()
     */
    public function isSelectedChoice(ChoiceView $choice, $selectedValue)
    {
        if (is_array($selectedValue)) {
            return false !== array_search($choice->value, $selectedValue, true);
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
