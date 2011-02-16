<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Renderer\Engine;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FieldInterface;

class TwigEngine implements EngineInterface
{
    protected $environment;
    protected $resources;

    public function __construct(\Twig_Environment $environment, array $resources = array())
    {
        $this->environment = $environment;
        $this->resources = $resources;
    }

    public function render(FieldInterface $field, $name, array $arguments, array $resources = null)
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
}