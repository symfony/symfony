<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Extension;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FieldInterface;
use Symfony\Component\Form\CollectionField;
use Symfony\Component\Form\HybridField;
use Symfony\Bundle\TwigBundle\TokenParser\FormThemeTokenParser;

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
     * Sets a theme for a given field.
     *
     * @param FieldInterface $field     A FieldInterface instance
     * @param array          $resources An array of resources
     */
    public function setTheme(FieldInterface $field, array $resources)
    {
        $this->themes->attach($field, $resources);
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
        return $this->render($field, 'field_row', array(
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
     * @param FieldInterface $field      The field to render
     * @param array          $attributes HTML attributes passed to the template
     * @param array          $parameters Additional variables passed to the template
     * @param array|string   $resources  A resource or array of resources
     */
    public function renderField(FieldInterface $field, array $attributes = array(), array $parameters = array(), $resources = null)
    {
        if (null !== $resources && !is_array($resources)) {
            $resources = array($resources);
        }

        return $this->render($field, 'field', array(
            'field'  => $field,
            'attr'   => $attributes,
            'params' => $parameters,
        ), $resources);
    }

    /**
     * Renders all hidden fields of the given field group
     *
     * @param FormInterface $group   The field group
     * @param array $params                Additional variables passed to the
     *                                     template
     */
    public function renderHidden(FormInterface $group, array $parameters = array())
    {
        return $this->render($group, 'hidden', array(
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
        return $this->render($field, 'errors', array(
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
        return $this->render($field, 'label', array(
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

    protected function render(FieldInterface $field, $name, array $arguments, array $resources = null)
    {
        if ('field' === $name) {
            list($name, $template) = $this->getWidget($field, $resources);
        } else {
            $template = $this->getTemplate($field, $name);
        }

        return $template->renderBlock($name, $arguments);
    }

    /**
     * @param FieldInterface $field The field to get the widget for
     * @param array $resources An array of template resources
     * @return array
     */
    protected function getWidget(FieldInterface $field, array $resources = null)
    {
        $class = get_class($field);
        $templates = $this->getTemplates($field, $resources);

        // find a template for the given class or one of its parents
        do {
            $parts = explode('\\', $class);
            $c = array_pop($parts);

            // convert the base class name (e.g. TextareaField) to underscores (e.g. textarea_field)
            $underscore = strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), strtr($c, '_', '.')));

            if (isset($templates[$underscore])) {
                return array($underscore, $templates[$underscore]);
            }
        } while (false !== $class = get_parent_class($class));

        throw new \RuntimeException(sprintf('Unable to render the "%s" field.', $field->getKey()));
    }

    protected function getTemplate(FieldInterface $field, $name, array $resources = null)
    {
        $templates = $this->getTemplates($field, $resources);

        return $templates[$name];
    }

    protected function getTemplates(FieldInterface $field, array $resources = null)
    {
        // templates are looked for in the following resources:
        //   * resources provided directly into the function call
        //   * resources from the themes (and its parents)
        //   * default resources

        // defaults
        $all = $this->resources;

        // themes
        $parent = $field;
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
