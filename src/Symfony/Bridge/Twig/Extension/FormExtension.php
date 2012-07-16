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
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;

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
     * @var \Symfony\Component\Form\FormRendererInterface
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
            'form_enctype'             => new \Twig_Function_Method($this, 'renderer->renderEnctype', array('is_safe' => array('html'))),
            'form_widget'              => new \Twig_Function_Method($this, 'renderer->renderWidget', array('is_safe' => array('html'))),
            'form_errors'              => new \Twig_Function_Method($this, 'renderer->renderErrors', array('is_safe' => array('html'))),
            'form_label'               => new \Twig_Function_Method($this, 'renderer->renderLabel', array('is_safe' => array('html'))),
            'form_row'                 => new \Twig_Function_Method($this, 'renderer->renderRow', array('is_safe' => array('html'))),
            'form_rest'                => new \Twig_Function_Method($this, 'renderer->renderRest', array('is_safe' => array('html'))),
            'csrf_token'               => new \Twig_Function_Method($this, 'renderer->renderCsrfToken'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'humanize'           => new \Twig_Filter_Method($this, 'renderer->humanize'),
            'is_choice_group'    => new \Twig_Filter_Function('is_array', array('is_safe' => array('html'))),
            'is_choice_selected' => new \Twig_Filter_Method($this, 'isChoiceSelected'),
        );
    }

    /**
     * Returns whether a choice is selected for a given form value.
     *
     * This method exists for the sole purpose that Twig performs (a lot) better
     * with filters than with methods of an object.
     *
     * To give this some perspective, I'm currently testing this on a form with
     * a large list of entity fields. Using the filter is around 220ms faster than
     * accessing the method directly on the object in the Twig template.
     *
     * @param ChoiceView   $choice        The choice to check.
     * @param string|array $selectedValue The selected value to compare.
     *
     * @return Boolean Whether the choice is selected.
     *
     * @see ChoiceView::isSelected()
     */
    public function isChoiceSelected(ChoiceView $choice, $selectedValue)
    {
        return $choice->isSelected($selectedValue);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'form';
    }
}
