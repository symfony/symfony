<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FieldGroupInterface;
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\CollectionField;
use Symfony\Component\Form\HybridField;
use Symfony\Bundle\TwigBundle\TokenParser\FormThemeTokenParser;

/**
 * FormExtension extends Twig with form capabilities.
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
        $this->resources = $resources;
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

    public function getFunctions()
    {
        return array(
            'form_enctype' => new \Twig_Function_Method($this, 'renderEnctype', array('is_safe' => array('html'))),
            'form_field'   => new \Twig_Function_Method($this, 'renderField', array('is_safe' => array('html'))),
            'form_hidden'  => new \Twig_Function_Method($this, 'renderHidden', array('is_safe' => array('html'))),
            'form_errors'  => new \Twig_Function_Method($this, 'renderErrors', array('is_safe' => array('html'))),
            'form_label'   => new \Twig_Function_Method($this, 'renderLabel', array('is_safe' => array('html'))),
            'form_data'    => new \Twig_Function_Method($this, 'renderData', array('is_safe' => array('html'))),
            'form_row'     => new \Twig_Function_Method($this, 'renderRow', array('is_safe' => array('html'))),
        );
    }

    /**
     * Renders the HTML enctype in the form tag, if necessary
     *
     * Example usage in Twig templates:
     *
     *     <form action="..." method="post" {{ form_enctype(form) }}>
     *
     * @param Form $form   The form for which to render the encoding type
     */
    public function renderEnctype(Form $form)
    {
        return $form->isMultipart() ? 'enctype="multipart/form-data"' : '';
    }

    /**
     * Renders a field row.
     *
     * @param FieldInterface $field  The field to render as a row
     */
    public function renderRow(FieldInterface $field)
    {
        if (null === $this->templates) {
            $this->templates = $this->resolveResources($this->resources);
        }

        return $this->templates['field_row']->renderBlock('field_row', array(
            'child'  => $field,
        ));
    }

    /**
     * Renders the HTML for an individual form field
     *
     * Example usage in Twig:
     *
     *     {{ form_field(field) }}
     *
     * You can pass attributes element during the call:
     *
     *     {{ form_field(field, {'class': 'foo'}) }}
     *
     * Some fields also accept additional variables as parameters:
     *
     *     {{ form_field(field, {}, {'separator': '+++++'}) }}
     *
     * @param FieldInterface $field  The field to render
     * @param array $params          Additional variables passed to the template
     * @param string $resources
     */
    public function renderField(FieldInterface $field, array $attributes = array(), array $parameters = array(), $resources = null)
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

        return $template->renderBlock($widget, array(
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

        return $this->templates['hidden']->renderBlock('hidden', array(
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

        return $this->templates['errors']->renderBlock('errors', array(
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

        return $this->templates['label']->renderBlock('label', array(
            'field'  => $field,
            'params' => $parameters,
            'label'  => null !== $label ? $label : ucfirst(strtolower(str_replace('_', ' ', $field->getKey()))),
        ));
    }

    /**
     * Renders the widget data of the given field
     *
     * @param FieldInterface $field The field to render the data for
     */
    public function renderData(FieldInterface $field)
    {
        return $field->getData();
    }

    /**
     * @param FieldInterface $field The field to get the widget for
     * @param array $resources An array of template resources
     * @return array
     */
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

            // convert the base class name (e.g. TextareaField) to underscores (e.g. textarea_field)
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
