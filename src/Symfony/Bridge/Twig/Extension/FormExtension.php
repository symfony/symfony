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
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Exception\FormException;

/**
 * FormExtension extends Twig with form capabilities.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class FormExtension extends \Twig_Extension
{
    protected $resources;
    protected $templates;
    protected $environment;
    protected $themes;

    public function __construct(array $resources = array())
    {
        $this->themes = new \SplObjectStorage();
        $this->resources = $resources;
    }

    /**
     * {@inheritdoc}
     */
    public function initRuntime(\Twig_Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * Sets a theme for a given view.
     *
     * @param FormView $view      A FormView instance
     * @param array    $resources An array of resources
     */
    public function setTheme(FormView $view, array $resources)
    {
        $this->themes->attach($view, $resources);
    }

    /**
     * Returns the token parser instance to add to the existing list.
     *
     * @return array An array of Twig_TokenParser instances
     */
    public function getTokenParsers()
    {
        return array(
            // {% form_theme form "SomeBungle::widgets.twig" %}
            new FormThemeTokenParser(),
        );
    }

    public function getFunctions()
    {
        return array(
            'form_enctype' => new \Twig_Function_Method($this, 'renderEnctype', array('is_safe' => array('html'))),
            'form_widget'  => new \Twig_Function_Method($this, 'renderWidget', array('is_safe' => array('html'))),
            'form_errors'  => new \Twig_Function_Method($this, 'renderErrors', array('is_safe' => array('html'))),
            'form_label'   => new \Twig_Function_Method($this, 'renderLabel', array('is_safe' => array('html'))),
            'form_row'     => new \Twig_Function_Method($this, 'renderRow', array('is_safe' => array('html'))),
            'form_rest'    => new \Twig_Function_Method($this, 'renderRest', array('is_safe' => array('html'))),
        );
    }

    /**
     * Renders the HTML enctype in the form tag, if necessary
     *
     * Example usage in Twig templates:
     *
     *     <form action="..." method="post" {{ form_enctype(form) }}>
     *
     * @param FormView $view  The view for which to render the encoding type
     */
    public function renderEnctype(FormView $view)
    {
        return $this->render($view, 'enctype');
    }

    /**
     * Renders a row for the view.
     *
     * @param FormView $view      The view to render as a row
     * @param array    $variables An array of variables
     */
    public function renderRow(FormView $view, array $variables = array())
    {
        return $this->render($view, 'row', $variables);
    }

    public function renderRest(FormView $view, array $variables = array())
    {
        return $this->render($view, 'rest', $variables);
    }

    /**
     * Renders the HTML for a given view
     *
     * Example usage in Twig:
     *
     *     {{ form_widget(view) }}
     *
     * You can pass attributes element during the call:
     *
     *     {{ form_widget(view, {'class': 'foo'}) }}
     *
     * Some fields also accept additional variables as parameters:
     *
     *     {{ form_widget(view, {}, {'separator': '+++++'}) }}
     *
     * @param FormView        $view       The view to render
     * @param array           $variables Additional variables passed to the template
     */
    public function renderWidget(FormView $view, array $variables = array())
    {
        return $this->render($view, 'widget', $variables);
    }

    /**
     * Renders the errors of the given view
     *
     * @param FormView $view The view to render the errors for
     */
    public function renderErrors(FormView $view)
    {
        return $this->render($view, 'errors');
    }

    /**
     * Renders the label of the given view
     *
     * @param FormView $view  The view to render the label for
     * @param string   $label Label name
     */
    public function renderLabel(FormView $view, $label = null)
    {
        return $this->render($view, 'label', null === $label ? array() : array('label' => $label));
    }

    protected function render(FormView $view, $section, array $variables = array())
    {
        $templates = $this->getTemplates($view);
        $blocks = $view->get('types');
        if ('widget' === $section || 'row' === $section) {
            array_unshift($blocks, '_'.$view->get('id'));
        }
        foreach ($blocks as &$block) {
            $block = $block.'_'.$section;

            if (isset($templates[$block])) {
                if ('widget' === $section || 'row' === $section) {
                    $view->setRendered();
                }

                return $templates[$block]->renderBlock($block, array_merge($view->all(), $variables));
            }
        }

        throw new FormException(sprintf('Unable to render form as none of the following blocks exist: "%s".', implode('", "', $blocks)));
    }

    protected function getTemplates(FormView $view)
    {
        // templates are looked for in the following resources:
        //   * resources from the themes (and its parents)
        //   * default resources

        // defaults
        $all = $this->resources;

        // themes
        $parent = $view;
        do {
            if (isset($this->themes[$parent])) {
                $all = array_merge($all, $this->themes[$parent]);
            }
        } while ($parent = $parent->getParent());

        $templates = array();
        foreach ($all as $resource) {
            if (!$resource instanceof \Twig_Template) {
                $resource = $this->environment->loadTemplate($resource);
            }

            $blocks = array();
            foreach ($this->getBlockNames($resource) as $name) {
                $blocks[$name] = $resource;
            }

            $templates = array_replace($templates, $blocks);
        }

        return $templates;
    }

    protected function getBlockNames($resource)
    {
        $names = $resource->getBlockNames();
        $parent = $resource;
        while (false !== $parent = $parent->getParent(array())) {
            $names = array_merge($names, $parent->getBlockNames());
        }

        return array_unique($names);
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'form';
    }
}
