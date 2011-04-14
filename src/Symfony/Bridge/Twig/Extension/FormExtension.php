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
use Symfony\Component\Form\TemplateContext;
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
     * Sets a theme for a given context.
     *
     * @param TemplateContext $context   A TemplateContext instance
     * @param array           $resources An array of resources
     */
    public function setTheme(TemplateContext $context, array $resources)
    {
        $this->themes->attach($context, $resources);
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
            'form_data'    => new \Twig_Function_Method($this, 'renderData', array('is_safe' => array('html'))),
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
     * @param TemplateContext $context  The context for which to render the encoding type
     */
    public function renderEnctype(TemplateContext $context)
    {
        return $this->render($context, 'enctype');
    }

    /**
     * Renders a row for the context.
     *
     * @param TemplateContext $context  The context to render as a row
     */
    public function renderRow(TemplateContext $context, array $variables = array())
    {
        $context->setRendered();

        return $this->render($context, 'row', $variables);
    }

    public function renderRest(TemplateContext $context, array $variables = array())
    {
        return $this->render($context, 'rest', $variables);
    }

    /**
     * Renders the HTML for a given context
     *
     * Example usage in Twig:
     *
     *     {{ form_widget(context) }}
     *
     * You can pass attributes element during the call:
     *
     *     {{ form_widget(context, {'class': 'foo'}) }}
     *
     * Some fields also accept additional variables as parameters:
     *
     *     {{ form_widget(context, {}, {'separator': '+++++'}) }}
     *
     * @param TemplateContext $context    The context to render
     * @param array           $attributes HTML attributes passed to the template
     * @param array           $parameters Additional variables passed to the template
     * @param array|string    $resources  A resource or array of resources
     */
    public function renderWidget(TemplateContext $context, array $variables = array(), $resources = null)
    {
        $context->setRendered();

        if (null !== $resources && !is_array($resources)) {
            $resources = array($resources);
        }

        return $this->render($context, 'widget', $variables, $resources);
    }

    /**
     * Renders the errors of the given context
     *
     * @param TemplateContext $context The context to render the errors for
     * @param array           $params  Additional variables passed to the template
     */
    public function renderErrors(TemplateContext $context)
    {
        return $this->render($context, 'errors');
    }

    /**
     * Renders the label of the given context
     *
     * @param TemplateContext $context The context to render the label for
     */
    public function renderLabel(TemplateContext $context, $label = null)
    {
        return $this->render($context, 'label', null === $label ? array() : array('label' => $label));
    }

    /**
     * Renders the widget data of the given context
     *
     * @param TemplateContext $context The context to render the data for
     */
    public function renderData(TemplateContext $context)
    {
        return $form->getData();
    }

    protected function render(TemplateContext $context, $section, array $variables = array(), array $resources = null)
    {
        $templates = $this->getTemplates($context, $resources);
        $blocks = $context->get('types');
        foreach ($blocks as &$block) {
            $block = $block.'__'.$section;

            if (isset($templates[$block])) {
                if ('widget' === $section) {
                    $context->set('is_rendered', true);
                }

                return $templates[$block]->renderBlock($block, array_merge($context->all(), $variables));
            }
        }

        throw new FormException(sprintf('Unable to render form as none of the following blocks exist: "%s".', implode('", "', $blocks)));
    }

    protected function getTemplate(TemplateContext $context, $name, array $resources = null)
    {
        $templates = $this->getTemplates($context, $resources);

        return $templates[$name];
    }

    protected function getTemplates(TemplateContext $context, array $resources = null)
    {
        // templates are looked for in the following resources:
        //   * resources provided directly into the function call
        //   * resources from the themes (and its parents)
        //   * default resources

        // defaults
        $all = $this->resources;

        // themes
        $parent = $context;
        do {
            if (isset($this->themes[$parent])) {
                $all = array_merge($all, $this->themes[$parent]);
            }
        } while ($parent = $parent->getParent());

        // local
        $all = array_merge($all, null !== $resources ? (array) $resources : array());

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
