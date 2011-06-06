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
    protected $varStack;

    public function __construct(array $resources = array())
    {
        $this->themes = new \SplObjectStorage();
        $this->varStack = new \SplObjectStorage();
        $this->templates = new \SplObjectStorage();

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
        $this->templates->detach($view);
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
     *
     * @return string The html markup
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
     *
     * @return string The html markup
     */
    public function renderRow(FormView $view, array $variables = array())
    {
        return $this->render($view, 'row', $variables);
    }

    /**
     * Renders views which have not already been rendered.
     *
     * @param FormView $view      The parent view
     * @param array    $variables An array of variables
     *
     * @return string The html markup
     */
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
     * You can pass options during the call:
     *
     *     {{ form_widget(view, {'attr': {'class': 'foo'}}) }}
     *
     *     {{ form_widget(view, {'separator': '+++++'}) }}
     *
     * @param FormView        $view      The view to render
     * @param array           $variables Additional variables passed to the template
     *
     * @return string The html markup
     */
    public function renderWidget(FormView $view, array $variables = array())
    {
        return $this->render($view, 'widget', $variables);
    }

    /**
     * Renders the errors of the given view
     *
     * @param FormView $view The view to render the errors for
     *
     * @return string The html markup
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
     * @param array    $variables Additional variables passed to the template
     *
     * @return string The html markup
     */
    public function renderLabel(FormView $view, $label = null, array $variables = array())
    {
        if ($label !== null) {
            $variables += array('label' => $label);
        }

        return $this->render($view, 'label', $variables);
    }

    /**
     * Renders a template.
     *
     * 1. This function first looks for a block named "_<view id>_<section>",
     * 2. if such a block is not found the function will look for a block named
     *    "<type name>_<section>",
     * 3. the type name is recursively replaced by the parent type name until a
     *    corresponding block is found
     *
     * @param FormView  $view       The form view
     * @param string    $section    The section to render (i.e. 'row', 'widget', 'label', ...)
     * @param array     $variables  Additional variables
     *
     * @return string The html markup
     *
     * @throws FormException if no template block exists to render the given section of the view
     */
    protected function render(FormView $view, $section, array $variables = array())
    {
        $templates = $this->getTemplates($view);
        $blocks = $view->get('types');
        array_unshift($blocks, '_'.$view->get('id'));

        foreach ($blocks as &$block) {
            $block = $block.'_'.$section;

            if (isset($templates[$block])) {
                if ('widget' === $section || 'row' === $section) {
                    $view->setRendered();
                }

                $this->varStack[$view] = array_replace(
                    $view->all(),
                    isset($this->varStack[$view]) ? $this->varStack[$view] : array(),
                    $variables
                );

                $html = $templates[$block]->renderBlock($block, $this->varStack[$view]);

                unset($this->varStack[$view]);

                return $html;
            }
        }

        throw new FormException(sprintf('Unable to render form as none of the following blocks exist: "%s".', implode('", "', $blocks)));
    }

    /**
     * Returns the templates used by the view.
     *
     * templates are looked for in the following resources:
     *   * resources from the themes (and its parents)
     *   * default resources
     *
     * @param FormView $view The view
     *
     * @return array An array of Twig_TemplateInterface instances
     */
    protected function getTemplates(FormView $view)
    {
        if (!$this->templates->contains($view)) {
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

            $this->templates->attach($view, $templates);
        } else {
            $templates = $this->templates[$view];
        }

        return $templates;
    }

    /**
     * Returns all the block defined in the template hierarchy.
     *
     * @param \Twig_Template $template
     *
     * @return array A list of block names
     */
    protected function getBlockNames(\Twig_Template $template)
    {
        $names = array();
        do {
            $names = array_merge($names, $template->getBlockNames());
        } while (false !== $template = $template->getParent(array()));

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
