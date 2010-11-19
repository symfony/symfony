<?php

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FieldGroupInterface;
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\CollectionField;
use Symfony\Component\Form\HybridField;
use Symfony\Bundle\TwigBundle\TokenParser\FormThemeTokenParser;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class FormExtension extends \Twig_Extension
{
    static protected $cache = array();

    protected $resources;
    protected $templates;
    protected $environment;
    protected $themes;

    public function __construct(array $resources = array())
    {
        $this->themes = new \SplObjectStorage();
        $this->resources = array_merge(array(
            'TwigBundle::form.twig',
        ), $resources);
    }

    /**
     * {@inheritdoc}
     */
    public function initRuntime(\Twig_Environment $environment)
    {
        $this->environment = $environment;
    }

    public function setTheme(FieldGroupInterface $group, array $resources)
    {
        $this->themes->attach($group, $resources);
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

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array(
            'render_enctype' => new \Twig_Filter_Method($this, 'renderEnctype', array('is_safe' => array('html'))),
            'render'         => new \Twig_Filter_Method($this, 'render', array('is_safe' => array('html'))),
            'render_hidden'  => new \Twig_Filter_Method($this, 'renderHidden', array('is_safe' => array('html'))),
            'render_errors'  => new \Twig_Filter_Method($this, 'renderErrors', array('is_safe' => array('html'))),
            'render_label'   => new \Twig_Filter_Method($this, 'renderLabel', array('is_safe' => array('html'))),
            'render_data'    => new \Twig_Filter_Method($this, 'renderData', array('is_safe' => array('html'))),
        );
    }

    /**
     * Renders the HTML enctype in the form tag, if necessary
     *
     * Example usage in Twig templates:
     *
     *     <form action="..." method="post" {{ form|render_enctype }}>
     *
     * @param Form $form   The form for which to render the encoding type
     */
    public function renderEnctype(Form $form)
    {
        return $form->isMultipart() ? 'enctype="multipart/form-data"' : '';
    }

    /**
     * Renders the HTML for an individual form field
     *
     * Example usage in Twig:
     *
     *     {{ field|render }}
     *
     * You can pass additional variables during the call:
     *
     *     {{ field|render(['param': 'value']) }}
     *
     * @param FieldInterface $field  The field to render
     * @param array $params          Additional variables passed to the template
     * @param string $resources
     */
    public function render(FieldInterface $field, array $attributes = array(), array $parameters = array(), $resources = null)
    {
        if (null === $this->templates) {
            $this->templates = $this->resolveResources($this->resources);
        }

        if (null !== $resources) {
            // The developer provided a custom theme in the filter call
            $resources = array($resources);
        } else {
            // The default theme is used
            $parent = $field;
            $resources = array();
            while ($parent = $parent->getParent()) {
                if (isset($this->themes[$parent])) {
                    $resources = $this->themes[$parent];
                }
            }
        }

        list($widget, $template) = $this->getWidget($field, $resources);

        return $template->getBlock($widget, array(
            'field'  => $field,
            'attr'   => $attributes,
            'params' => $parameters,
        ));
    }

    /**
     * Renders all hidden fields of the given field group
     *
     * @param FieldGroupInterface $group   The field group
     * @param array $params                Additional variables passed to the
     *                                     template
     */
    public function renderHidden(FieldGroupInterface $group, array $parameters = array())
    {
        if (null === $this->templates) {
            $this->templates = $this->resolveResources($this->resources);
        }

        return $this->templates['hidden']->getBlock('hidden', array(
            'field'  => $group,
            'params' => $parameters,
        ));
    }

    /**
     * Renders the errors of the given field
     *
     * @param FieldInterface $field  The field to render the errors for
     * @param array $params          Additional variables passed to the template
     */
    public function renderErrors(FieldInterface $field, array $parameters = array())
    {
        if (null === $this->templates) {
            $this->templates = $this->resolveResources($this->resources);
        }

        return $this->templates['errors']->getBlock('errors', array(
            'field'  => $field,
            'params' => $parameters,
        ));
    }

    /**
     * Renders the label of the given field
     *
     * @param FieldInterface $field  The field to render the label for
     * @param array $params          Additional variables passed to the template
     */
    public function renderLabel(FieldInterface $field, $label = null, array $parameters = array())
    {
        if (null === $this->templates) {
            $this->templates = $this->resolveResources($this->resources);
        }

        return $this->templates['label']->getBlock('label', array(
            'field'  => $field,
            'params' => $parameters,
            'label'  => null !== $label ? $label : ucfirst(strtolower(str_replace('_', ' ', $field->getKey()))),
        ));
    }

    protected function getWidget(FieldInterface $field, array $resources = array())
    {
        $cacheable = true;
        $templates = array();
        if ($resources) {
            $templates = $this->resolveResources($resources);
            $cacheable = false;
        }

        // add "global" templates as fallback
        $templates = array_merge($this->templates, $templates);

        $class = get_class($field);

        if (true === $cacheable && isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        // find a template for the given class or one of its parents
        do {
            $parts = explode('\\', $class);
            $c = array_pop($parts);

            $underscore = strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($c, '_', '.')));

            if (isset($templates[$underscore])) {
                if (true === $cacheable) {
                    self::$cache[$class] = array($underscore, $templates[$underscore]);
                }

                return array($underscore, $templates[$underscore]);
            }
        } while (false !== $class = get_parent_class($class));

        throw new \RuntimeException(sprintf('Unable to render the "%s" field.', $field->getKey()));
    }

    protected function resolveResources(array $resources)
    {
        $templates = array();
        foreach ($resources as $resource)
        {
            $blocks = $this->resolveTemplate($this->environment->loadTemplate($resource));

            $templates = array_replace($templates, $blocks);
        }

        return $templates;
    }

    protected function resolveTemplate($template)
    {
        // an array of blockName => template
        $blocks = array();
        foreach ($template->getBlockNames() as $name) {
            $blocks[$name] = $template;
        }

        return $blocks;
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
